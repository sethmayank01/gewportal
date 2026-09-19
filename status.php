<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';
require 'includes/general_config.php';


/*
|--------------------------------------------------------------------------
| Job Type Filter
|--------------------------------------------------------------------------
|
| RECENT = Recently updated New Transformer and Repair jobs
| TRFD   = New Transformer
| TRFR   = Repair
| ALL    = All Jobs
|
*/

$jobType = $_GET['type'] ?? 'RECENT';

if (!in_array($jobType, ['RECENT', 'TRFD', 'TRFR', 'ALL'], true)) {
    $jobType = 'RECENT';
}

$recentDays = (int)(
    $_GET['days']
    ?? $recentStatusDays
);

if ($recentDays < 1 || $recentDays > 365) {
    $recentDays = $recentStatusDays;
}

$activityCode = $_GET['activity'] ?? 'ALL';

$validActivityCodes =
    array_keys(
        $processDefinitions
    );

if (
    $activityCode !== 'ALL'
    &&
    !in_array(
        $activityCode,
        $validActivityCodes,
        true
    )
) {
    $activityCode = 'ALL';
}

$displayMode = $_GET['view'] ?? 'JOB';

if (!in_array($displayMode, ['JOB', 'DATE'], true)) {
    $displayMode = 'JOB';
}



/*
|--------------------------------------------------------------------------
| Get Jobs
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];
$processJoinSql = '';

if ($activityCode !== 'ALL') {
    $processJoinSql = "
       AND dpi.process_code = :selected_activity_code
    ";

    $params['selected_activity_code'] = $activityCode;
}

if ($jobType === 'RECENT') {

    $where[] = "
        (
            j.serial_no LIKE 'TRFD%'
            OR
            j.serial_no LIKE 'TRFR%'
        )
    ";

    $where[] = "
        EXISTS
        (
            SELECT 1
            FROM job_process_items recent_dpi
            INNER JOIN job_process_daily_status recent_ds
                ON recent_ds.job_process_item_id = recent_dpi.id
            WHERE recent_dpi.job_serial_no = j.serial_no
              " . (
                    $activityCode !== 'ALL'
                        ? "AND recent_dpi.process_code = :recent_activity_code"
                        : ''
                ) . "
              " . (
                    $activityCode !== 'ALL' || $displayMode === 'DATE'
                        ? "AND recent_ds.status_code IS NOT NULL
                           AND recent_ds.progress_date >=
                               CURRENT_DATE - (CAST(:recent_days AS integer) - 1)"
                        : "AND recent_ds.updated_at >=
                               CURRENT_TIMESTAMP - make_interval(days => :recent_days)"
                ) . "
        )
    ";

    $params['recent_days'] = $recentDays;

    if ($activityCode !== 'ALL') {
        $params['recent_activity_code'] = $activityCode;
    }

}
elseif ($jobType === 'TRFD') {

    $where[] = "j.serial_no LIKE 'TRFD%'";

}
elseif ($jobType === 'TRFR') {

    $where[] = "j.serial_no LIKE 'TRFR%'";

}
else {

    $where[] = "
        (
            j.serial_no LIKE 'TRFD%'
            OR
            j.serial_no LIKE 'TRFR%'
        )
    ";

}

if (($activityCode !== 'ALL' || $displayMode === 'DATE') && $jobType !== 'RECENT') {
    $where[] = "
        EXISTS
        (
            SELECT 1
            FROM job_process_items period_dpi
            INNER JOIN job_process_daily_status period_ds
                ON period_ds.job_process_item_id = period_dpi.id
            WHERE period_dpi.job_serial_no = j.serial_no
              " . (
                    $activityCode !== 'ALL'
                        ? 'AND period_dpi.process_code = :period_activity_code'
                        : ''
                ) . "
              AND period_dpi.active = TRUE
              AND period_ds.status_code IS NOT NULL
              AND period_ds.progress_date >=
                    CURRENT_DATE - (CAST(:period_days AS integer) - 1)
        )
    ";

    if ($activityCode !== 'ALL') {
        $params['period_activity_code'] = $activityCode;
    }
    $params['period_days'] = $recentDays;
}



$whereSql =
    implode(
        ' AND ',
        $where
    );


$sql = "
    SELECT
        j.serial_no,
        j.data,

        dpi.id AS process_item_id,
        dpi.process_code,
        dpi.item_code,
        dpi.item_name,

        ds.status_code,
		ds.planned_status_code,
		ds.progress_date,
		ds.updated_at,
		ds.updated_by

    FROM jobs j

    LEFT JOIN job_process_items dpi
        ON dpi.job_serial_no = j.serial_no
       AND dpi.active = TRUE
       {$processJoinSql}

    LEFT JOIN LATERAL
    (
        SELECT
            dps.status_code,
			dps.planned_status_code,
            dps.progress_date,
            dps.updated_at,
            dps.updated_by
        FROM job_process_daily_status dps
        WHERE
            dps.job_process_item_id = dpi.id
        ORDER BY
            dps.progress_date DESC,
            dps.updated_at DESC
        LIMIT 1
    ) ds
        ON TRUE

    WHERE
        {$whereSql}

    ORDER BY
        MAX(ds.updated_at) OVER (PARTITION BY j.serial_no) DESC NULLS LAST,
        j.serial_no DESC,
        dpi.process_code,
        dpi.item_code
";


$stmt =
    $pdo->prepare($sql);

$stmt->execute($params);

$rows =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build Job / Process / Status Structure
|--------------------------------------------------------------------------
*/

$jobs = [];


/*
|--------------------------------------------------------------------------
| Process display order
|--------------------------------------------------------------------------
|
| Follow the order in general_config.php.
|
*/

$processOrder =
    $activityCode === 'ALL'
        ? array_keys(
            $processDefinitions
        )
        : [
            $activityCode
        ];


foreach ($rows as $row) {

    $serialNo =
        $row['serial_no'];


    /*
    |--------------------------------------------------------------------------
    | Initialize Job
    |--------------------------------------------------------------------------
    */

    if (!isset($jobs[$serialNo])) {

        $jobData = [];

        if (!empty($row['data'])) {

            $decoded =
                json_decode(
                    $row['data'],
                    true
                );

            if (is_array($decoded)) {
                $jobData = $decoded;
            }

        }


        $jobs[$serialNo] = [

            'serial_no' =>
                $serialNo,

            'data' =>
                $jobData,

            'processes' =>
                []

        ];

    }


    /*
    |--------------------------------------------------------------------------
    | Process Item
    |--------------------------------------------------------------------------
    */

    if (
        empty(
            $row['process_item_id']
        )
    ) {
        continue;
    }


    $processCode =
        $row['process_code'];


    if (
        !isset(
            $jobs[
                $serialNo
            ]['processes'][
                $processCode
            ]
        )
    ) {

        $jobs[
            $serialNo
        ]['processes'][
            $processCode
        ] = [];

    }


    /*
    |--------------------------------------------------------------------------
    | Latest Status
    |--------------------------------------------------------------------------
    */

    $statusCode =
        trim(
            $row['status_code']
            ?? ''
        );


    if ($statusCode === '') {
        $statusCode = 'NOT_UPDATED';
    }
	
	$plannedStatusCode =
    trim(
        $row['planned_status_code']
        ?? ''
    );

if ($plannedStatusCode === '') {
    $plannedStatusCode = 'NOT_PLANNED';
}


    $jobs[
    $serialNo
]['processes'][
    $processCode
][] = [

    'item_code' =>
        $row['item_code'],

    'item_name' =>
        $row['item_name'],

    'actual_status' =>
        $statusCode,

    'planned_status' =>
        $plannedStatusCode,

    'progress_date' =>
        $row['progress_date'],

    'updated_at' =>
        $row['updated_at'],

    'updated_by' =>
        $row['updated_by']

];

}


/*
|--------------------------------------------------------------------------
| Selected Activity History
|--------------------------------------------------------------------------
|
| In single-activity mode the table shows every actual update recorded in
| the selected calendar-day period, rather than only each item's last state.
|
*/

$activityHistory = [];
$activityHistoryByDate = [];

if (($activityCode !== 'ALL' || $displayMode === 'DATE') && !empty($jobs)) {
    $historyPlaceholders = [];
    $historyParams = [
        'history_days' => $recentDays
    ];

    if ($activityCode !== 'ALL') {
        $historyParams['history_activity_code'] = $activityCode;
    }

    foreach (array_keys($jobs) as $index => $serialNo) {
        $placeholder = ':history_job_' . $index;
        $historyPlaceholders[] = $placeholder;
        $historyParams[$placeholder] = $serialNo;
    }

    $historySql = "
        SELECT
            dpi.job_serial_no,
            dpi.process_code,
            dpi.item_code,
            dpi.item_name,
            ds.progress_date,
            ds.status_code,
            ds.remarks,
            ds.updated_by,
            ds.updated_at
        FROM job_process_items dpi
        INNER JOIN job_process_daily_status ds
            ON ds.job_process_item_id = dpi.id
        WHERE dpi.active = TRUE
          " . (
                $activityCode !== 'ALL'
                    ? 'AND dpi.process_code = :history_activity_code'
                    : ''
            ) . "
          AND dpi.job_serial_no IN (
              " . implode(',', $historyPlaceholders) . "
          )
          AND ds.status_code IS NOT NULL
          AND ds.progress_date >=
                CURRENT_DATE - (CAST(:history_days AS integer) - 1)
        ORDER BY
            dpi.job_serial_no,
            ds.progress_date DESC,
            ds.updated_at DESC,
            dpi.item_code
    ";

    $historyStmt = $pdo->prepare($historySql);
    $historyStmt->execute($historyParams);

    foreach ($historyStmt->fetchAll() as $historyRow) {
        $activityHistory[
            $historyRow['job_serial_no']
        ][
            $historyRow['process_code']
        ][
            $historyRow['progress_date']
        ][] = $historyRow;

        $activityHistoryByDate[
            $historyRow['progress_date']
        ][
            $historyRow['job_serial_no']
        ][
            $historyRow['process_code']
        ][] = $historyRow;
    }
}


/*
|--------------------------------------------------------------------------
| Build Activity Timelines
|--------------------------------------------------------------------------
|
| An activity starts with its first meaningful actual-status entry. It is
| complete only when every active item in that activity has been completed.
|
*/

$jobTimelines = [];

if (!empty($jobs)) {

    $timelinePlaceholders = [];
    $timelineParams = [];

    foreach (array_keys($jobs) as $index => $serialNo) {
        $placeholder = ':timeline_job_' . $index;
        $timelinePlaceholders[] = $placeholder;
        $timelineParams[$placeholder] = $serialNo;
    }

    $timelineSql = "
        SELECT
            dpi.job_serial_no,
            dpi.process_code,
            dpi.item_code,
            MIN(ds.progress_date) FILTER (
                WHERE ds.status_code IS NOT NULL
                  AND ds.status_code <> 'NOT_STARTED'
            ) AS started_on,
            MIN(ds.progress_date) FILTER (
                WHERE ds.status_code = 'COMPLETE'
            ) AS completed_on
        FROM job_process_items dpi
        LEFT JOIN job_process_daily_status ds
            ON ds.job_process_item_id = dpi.id
        WHERE dpi.active = TRUE
          AND dpi.job_serial_no IN (
              " . implode(',', $timelinePlaceholders) . "
          )
        GROUP BY
            dpi.job_serial_no,
            dpi.process_code,
            dpi.item_code
        ORDER BY
            dpi.job_serial_no,
            dpi.process_code,
            dpi.item_code
    ";

    $timelineStmt = $pdo->prepare($timelineSql);
    $timelineStmt->execute($timelineParams);

    $timelineProcesses = [];

    foreach ($timelineStmt->fetchAll() as $timelineRow) {
        $serialNo = $timelineRow['job_serial_no'];
        $processCode = $timelineRow['process_code'];

        if (!isset($timelineProcesses[$serialNo][$processCode])) {
            $timelineProcesses[$serialNo][$processCode] = [
                'item_count' => 0,
                'completed_count' => 0,
                'started_on' => null,
                'completed_on' => null
            ];
        }

        $processTimeline = &$timelineProcesses[$serialNo][$processCode];
        $processTimeline['item_count']++;

        if (
            !empty($timelineRow['started_on'])
            && (
                $processTimeline['started_on'] === null
                || $timelineRow['started_on'] < $processTimeline['started_on']
            )
        ) {
            $processTimeline['started_on'] = $timelineRow['started_on'];
        }

        if (!empty($timelineRow['completed_on'])) {
            $processTimeline['completed_count']++;

            if (
                $processTimeline['completed_on'] === null
                || $timelineRow['completed_on'] > $processTimeline['completed_on']
            ) {
                $processTimeline['completed_on'] = $timelineRow['completed_on'];
            }
        }

        unset($processTimeline);
    }

    foreach ($jobs as $serialNo => $job) {
        $activities = [];

        foreach (array_keys($processDefinitions) as $processCode) {
            $processTimeline = $timelineProcesses[$serialNo][$processCode] ?? null;

            if ($processTimeline === null) {
                continue;
            }

            $completedOn =
                $processTimeline['item_count'] > 0
                && $processTimeline['completed_count'] === $processTimeline['item_count']
                    ? $processTimeline['completed_on']
                    : null;

            $activities[] = [
                'name' => processName($processCode, $processDefinitions),
                'started_on' => $processTimeline['started_on'],
                'completed_on' => $completedOn,
                'completed_items' => $processTimeline['completed_count'],
                'item_count' => $processTimeline['item_count']
            ];
        }

        $jobTimelines[$serialNo] = [
            'serial_no' => $serialNo,
            'purchaser' => $job['data']['purchaserName'] ?? '',
            'job_url' => 'job.php?job=' . urlencode($serialNo),
            'activities' => $activities
        ];
    }
}


/*
|--------------------------------------------------------------------------
| Helper: Process Name
|--------------------------------------------------------------------------
*/

function processName(
    string $processCode,
    array $processDefinitions
): string {

    return
        $processDefinitions[
            $processCode
        ]['name']
        ??
        $processCode;

}


/*
|--------------------------------------------------------------------------
| Helper: Status Name
|--------------------------------------------------------------------------
*/

function statusName(
    string $statusCode,
    array $processStatuses
): string {

    if ($statusCode === 'NOT_UPDATED') {
        return 'Not Updated';
    }
	
	 if ($statusCode === 'NOT_PLANNED') {
        return 'Not Planned';
    }

	return
        $processStatuses[
            $statusCode
        ]['name']
        ??
        $statusCode;

}


/*
|--------------------------------------------------------------------------
| Helper: Status CSS Class
|--------------------------------------------------------------------------
*/

function statusClass(
    string $statusCode
): string {

    switch ($statusCode) {

        case 'COMPLETE':
            return 'status-success';

        case 'UNDER_PROCESS':
            return 'status-info';

        case 'HOLD':
            return 'status-warning';

        case 'MATERIAL_AVAILABLE':
            return 'status-success';

        case 'DISPATCHED':
            return 'status-info';

        case 'ORDERED':
            return 'status-warning';

        case 'ORDER_PENDING':
            return 'status-warning';

        case 'DRAWING_AVAILABLE':
            return 'status-info';

        case 'DRAWING_PENDING':
            return 'status-warning';
		
		case 'NOT_PLANNED':
			return 'status-missing';

        case 'NOT_STARTED':
            return 'status-missing';

        case 'NOT_UPDATED':
        default:
            return 'status-missing';

    }

}


$pageTitle =
    'Production Status';


require 'includes/header.php';

?>


<div class="container">


    <!-- =====================================================
         PAGE HEADER / FILTERS
         ===================================================== -->

    <div class="card">
		 <a class="back" href="jobs.php">

        ← Back to Jobs

    </a>

        <div class="status-page-header">

            <div>

                <h2 class="card-title">
                    Production Status
                </h2>

                <div class="muted">

                    Current status of each main activity
                    and its process items.

                </div>

            </div>


            <div class="status-filters">


                <form
    method="GET"
    class="status-filter-form"
>

                    <div>

                        <label
                            class="form-label"
                            for="jobType"
                        >
                            Job Type
                        </label>

                        <select
                            id="jobType"
                            name="type"
                            class="form-control status-filter-select"
                            onchange="this.form.submit()"
                        >

                            <option
                                value="RECENT"
                                <?= $jobType === 'RECENT'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Recent (<?= $recentDays ?> Days)
                            </option>

                            <option
                                value="TRFD"
                                <?= $jobType === 'TRFD'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                New Transformer
                            </option>


                            <option
                                value="TRFR"
                                <?= $jobType === 'TRFR'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                Repair
                            </option>


                            <option
                                value="ALL"
                                <?= $jobType === 'ALL'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                All
                            </option>

                        </select>

                    </div>


                    <div>

                        <label
                            class="form-label"
                            for="displayMode"
                        >
                            Display
                        </label>

                        <select
                            id="displayMode"
                            name="view"
                            class="form-control status-filter-select"
                            onchange="this.form.submit()"
                        >
                            <option
                                value="JOB"
                                <?= $displayMode === 'JOB' ? 'selected' : '' ?>
                            >
                                By Job
                            </option>
                            <option
                                value="DATE"
                                <?= $displayMode === 'DATE' ? 'selected' : '' ?>
                            >
                                By Date
                            </option>
                        </select>

                    </div>


                    <div>

                        <label
                            class="form-label"
                            for="activityCode"
                        >
                            Activity
                        </label>

                        <select
                            id="activityCode"
                            name="activity"
                            class="form-control status-filter-select"
                            onchange="this.form.submit()"
                        >

                            <option
                                value="ALL"
                                <?= $activityCode === 'ALL'
                                    ? 'selected'
                                    : ''
                                ?>
                            >
                                All
                            </option>

                            <?php foreach (
                                $processDefinitions
                                as $processCode => $process
                            ): ?>

                                <option
                                    value="<?= htmlspecialchars(
                                        $processCode
                                    ) ?>"
                                    <?= $activityCode === $processCode
                                        ? 'selected'
                                        : ''
                                    ?>
                                >
                                    <?= htmlspecialchars(
                                        $process['name']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div>

                        <label
                            class="form-label"
                            for="recentDays"
                        >
                            <?= $activityCode === 'ALL' && $displayMode === 'JOB'
                                ? 'Updated within'
                                : 'Activity period'
                            ?>
                        </label>

                        <input
                            type="number"
                            id="recentDays"
                            name="days"
                            class="form-control status-filter-select"
                            min="1"
                            max="365"
                            value="<?= $recentDays ?>"
                            onchange="this.form.submit()"
                        >

                    </div>


                    <div>

                        <label
                            class="form-label"
                            for="jobSearch"
                        >
                            Search
                        </label>

                      <input
    type="text"
    id="jobSearch"
    class="form-control status-search"
    placeholder="Job No. / Purchaser"
    autocomplete="off"
>

                    </div>


                    

                </form>


            </div>

        </div>

    </div>


    <!-- =====================================================
         STATUS TABLE
         ===================================================== -->

    <div class="card">

        <?php if (empty($jobs)): ?>


            <div class="empty">

                No jobs found.

            </div>


        <?php else: ?>

            <?php if ($displayMode === 'DATE'): ?>

                <?php if (empty($activityHistoryByDate)): ?>

                    <div class="empty">
                        No activity updates found in this period.
                    </div>

                <?php else: ?>

                    <div class="status-table-container">

                        <table class="status-table status-date-table">

                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Jobs and activities performed</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $activityHistoryByDate
                                    as $historyDate => $dateJobs
                                ): ?>

                                    <?php
                                    $dateSearchParts = [];

                                    foreach ($dateJobs as $dateSerialNo => $dateProcesses) {
                                        $dateSearchParts[] = $dateSerialNo;
                                        $dateSearchParts[] =
                                            $jobs[$dateSerialNo]['data']['purchaserName']
                                            ?? '';

                                        foreach ($dateProcesses as $dateProcessCode => $dateEntries) {
                                            $dateSearchParts[] = processName(
                                                $dateProcessCode,
                                                $processDefinitions
                                            );

                                            foreach ($dateEntries as $dateEntry) {
                                                $dateSearchParts[] = $dateEntry['item_code'];
                                                $dateSearchParts[] = $dateEntry['item_name'];
                                            }
                                        }
                                    }
                                    ?>

                                    <tr
                                        class="status-job-row"
                                        data-search="<?= htmlspecialchars(
                                            strtolower(implode(' ', $dateSearchParts)),
                                            ENT_QUOTES
                                        ) ?>"
                                    >
                                        <td data-label="Date" class="status-date-cell">
                                            <time datetime="<?= htmlspecialchars(
                                                $historyDate,
                                                ENT_QUOTES
                                            ) ?>">
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'd M Y',
                                                            strtotime($historyDate)
                                                        )
                                                    ) ?>
                                                </strong>
                                                <span>
                                                    <?= htmlspecialchars(
                                                        date(
                                                            'l',
                                                            strtotime($historyDate)
                                                        )
                                                    ) ?>
                                                </span>
                                            </time>
                                        </td>

                                        <td data-label="Work performed">
                                            <div class="date-job-list">

                                                <?php foreach (
                                                    $dateJobs
                                                    as $dateSerialNo => $dateProcesses
                                                ): ?>

                                                    <?php
                                                    $dateJobData =
                                                        $jobs[$dateSerialNo]['data']
                                                        ?? [];
                                                    ?>

                                                    <section class="date-job-entry">
                                                        <div class="date-job-heading">
                                                            <a
                                                                class="job-link"
                                                                href="job_timeline.php?job=<?= urlencode(
                                                                    $dateSerialNo
                                                                ) ?>"
                                                            >
                                                                <?= htmlspecialchars($dateSerialNo) ?>
                                                            </a>

                                                            <span>
                                                                <?= htmlspecialchars(
                                                                    $dateJobData['purchaserName']
                                                                    ?? ''
                                                                ) ?>
                                                            </span>
                                                        </div>

                                                        <?php foreach (
                                                            $dateProcesses
                                                            as $dateProcessCode => $dateEntries
                                                        ): ?>

                                                            <div class="date-activity-group">
                                                                <div class="date-activity-name">
                                                                    <?= htmlspecialchars(
                                                                        processName(
                                                                            $dateProcessCode,
                                                                            $processDefinitions
                                                                        )
                                                                    ) ?>
                                                                </div>

                                                                <div class="date-activity-items">
                                                                    <?php foreach ($dateEntries as $dateEntry): ?>
                                                                        <div class="date-activity-item">
                                                                            <div class="date-activity-item-heading">
                                                                                <strong>
                                                                                    <?= htmlspecialchars(
                                                                                        $dateEntry['item_code']
                                                                                    ) ?>
                                                                                </strong>
                                                                                <span class="status <?= htmlspecialchars(
                                                                                    statusClass(
                                                                                        $dateEntry['status_code']
                                                                                    )
                                                                                ) ?>">
                                                                                    <?= htmlspecialchars(
                                                                                        statusName(
                                                                                            $dateEntry['status_code'],
                                                                                            $processStatuses
                                                                                        )
                                                                                    ) ?>
                                                                                </span>
                                                                            </div>

                                                                            <div class="date-activity-item-name">
                                                                                <?= htmlspecialchars(
                                                                                    $dateEntry['item_name']
                                                                                ) ?>
                                                                            </div>

                                                                            <?php if (!empty($dateEntry['remarks'])): ?>
                                                                                <div class="date-activity-remarks">
                                                                                    <?= nl2br(
                                                                                        htmlspecialchars(
                                                                                            $dateEntry['remarks']
                                                                                        )
                                                                                    ) ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>

                                                        <?php endforeach; ?>
                                                    </section>

                                                <?php endforeach; ?>

                                            </div>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            <?php else: ?>

            <div class="status-table-container">

                <table class="status-table <?= $activityCode !== 'ALL'
                    ? 'status-history-table'
                    : ''
                ?>">


                    <thead>

                        <tr>

                            <th>
                                Job No.
                            </th>

                            <th>
                                Purchaser
                            </th>

                            <th>
                                kVA
                            </th>


                            <?php foreach (
                                $processOrder
                                as $processCode
                            ): ?>

                                <th>
                                    <?= htmlspecialchars(
                                        processName(
                                            $processCode,
                                            $processDefinitions
                                        )
                                    ) ?>
                                </th>

                            <?php endforeach; ?>


                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $jobs
                        as $job
                    ): ?>


                        <?php

                        $jobData =
                            $job['data'];

                        ?>


                        <tr
    class="status-job-row"
    data-search="<?= htmlspecialchars(
        strtolower(
            $job['serial_no']
            . ' '
            . ($jobData['purchaserName'] ?? '')
        ),
        ENT_QUOTES
    ) ?>"
>


                            <td data-label="Job No.">

                                <a
                                    class="job-link"
                                    href="job_timeline.php?job=<?= urlencode(
                                        $job['serial_no']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $job['serial_no']
                                    ) ?>

                                </a>

                            </td>


                            <td data-label="Purchaser">

                                <?= htmlspecialchars(
                                    $jobData[
                                        'purchaserName'
                                    ] ?? ''
                                ) ?>

                            </td>


                            <td data-label="kVA">

                                <?= htmlspecialchars(
                                    $jobData[
                                        'kva'
                                    ] ?? ''
                                ) ?>

                            </td>


                            <?php foreach (
                                $processOrder
                                as $processCode
                            ): ?>


                                <td
                                    class="status-process-cell"
                                    data-label="<?= htmlspecialchars(
                                        processName(
                                            $processCode,
                                            $processDefinitions
                                        ),
                                        ENT_QUOTES
                                    ) ?>"
                                >


                                    <?php

                                    $processItems =
                                        $job[
                                            'processes'
                                        ][$processCode]
                                        ?? [];

                                    $historyDays =
                                        $activityHistory[
                                            $job['serial_no']
                                        ][$processCode]
                                        ?? [];

                                    ?>


                                    <?php if ($activityCode !== 'ALL'): ?>

                                        <?php if (empty($historyDays)): ?>

                                            <span class="muted">
                                                No updates in this period
                                            </span>

                                        <?php else: ?>

                                            <div class="activity-history">

                                                <?php foreach (
                                                    $historyDays
                                                    as $historyDate => $historyEntries
                                                ): ?>

                                                    <div class="activity-history-day">

                                                        <time
                                                            class="activity-history-date"
                                                            datetime="<?= htmlspecialchars(
                                                                $historyDate,
                                                                ENT_QUOTES
                                                            ) ?>"
                                                        >
                                                            <?= htmlspecialchars(
                                                                date(
                                                                    'd M Y',
                                                                    strtotime($historyDate)
                                                                )
                                                            ) ?>
                                                        </time>

                                                        <div class="activity-history-entries">

                                                            <?php foreach (
                                                                $historyEntries
                                                                as $historyEntry
                                                            ): ?>

                                                                <div class="activity-history-entry">

                                                                    <div class="activity-history-entry-heading">
                                                                        <strong>
                                                                            <?= htmlspecialchars(
                                                                                $historyEntry['item_code']
                                                                            ) ?>
                                                                        </strong>

                                                                        <span
                                                                            class="status <?= htmlspecialchars(
                                                                                statusClass(
                                                                                    $historyEntry['status_code']
                                                                                )
                                                                            ) ?>"
                                                                        >
                                                                            <?= htmlspecialchars(
                                                                                statusName(
                                                                                    $historyEntry['status_code'],
                                                                                    $processStatuses
                                                                                )
                                                                            ) ?>
                                                                        </span>
                                                                    </div>

                                                                    <div class="activity-history-item-name">
                                                                        <?= htmlspecialchars(
                                                                            $historyEntry['item_name']
                                                                        ) ?>
                                                                    </div>

                                                                    <?php if (!empty($historyEntry['remarks'])): ?>
                                                                        <div class="activity-history-remarks">
                                                                            <?= nl2br(
                                                                                htmlspecialchars(
                                                                                    $historyEntry['remarks']
                                                                                )
                                                                            ) ?>
                                                                        </div>
                                                                    <?php endif; ?>

                                                                </div>

                                                            <?php endforeach; ?>

                                                        </div>

                                                    </div>

                                                <?php endforeach; ?>

                                            </div>

                                        <?php endif; ?>

                                    <?php elseif (
                                        empty(
                                            $processItems
                                        )
                                    ): ?>

                                        <span class="muted">
                                            —
                                        </span>

                                    <?php else: ?>


                                        <div
                                            class="
                                                status-groups
                                            "
                                        >


                                            <?php

$actualGroups = [];
$plannedGroups = [];

foreach (
    $processItems
    as $item
) {

    $actualStatus =
        $item['actual_status'];

    $plannedStatus =
        $item['planned_status'];


    $actualGroups[
        $actualStatus
    ][] =
        $item;


    $plannedGroups[
        $plannedStatus
    ][] =
        $item;

}

?>

<div class="status-summary">

    <!-- ACTUAL -->

    <div class="status-summary-section">

        <div class="status-summary-label">
            Actual
        </div>


        <?php foreach (
            $actualGroups
            as $statusCode => $items
        ): ?>

            <?php

            $itemCodes = [];

            $latestActualDate = null;

            foreach (
                $items
                as $item
            ) {

                $itemCodes[] =
                    $item['item_code'];

                if (
                    !empty(
                        $item['progress_date']
                    )
                ) {

                    if (
                        $latestActualDate === null
                        ||
                        $item['progress_date']
                            > $latestActualDate
                    ) {

                        $latestActualDate =
                            $item['progress_date'];

                    }

                }

            }

            ?>

            <div class="status-group">

                <span
                    class="
                        status
                        <?= htmlspecialchars(
                            statusClass(
                                $statusCode
                            )
                        ) ?>
                    "
                >

                    <?= htmlspecialchars(
                        statusName(
                            $statusCode,
                            $processStatuses
                        )
                    ) ?>

                </span>


                <div class="status-items">

                    <?= htmlspecialchars(
                        implode(
                            ' | ',
                            $itemCodes
                        )
                    ) ?>

                </div>


                <?php if (
                    $latestActualDate
                ): ?>

                    <div class="status-updated-date">

                        Updated:
                        <?= htmlspecialchars(
                            date(
                                'd-M-Y',
                                strtotime(
                                    $latestActualDate
                                )
                            )
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>


    <!-- PLAN -->

    <div class="status-summary-section">

        <div class="status-summary-label">
            Plan
        </div>


        <?php foreach (
            $plannedGroups
            as $statusCode => $items
        ): ?>

            <?php if (
                $statusCode === 'NOT_PLANNED'
            ) {
                continue;
            }
            ?>

            <?php

            $itemCodes = [];

            foreach (
                $items
                as $item
            ) {

                $itemCodes[] =
                    $item['item_code'];

            }

            ?>

            <div class="status-group">

                <span
                    class="
                        status
                        <?= htmlspecialchars(
                            statusClass(
                                $statusCode
                            )
                        ) ?>
                    "
                >

                    <?= htmlspecialchars(
                        statusName(
                            $statusCode,
                            $processStatuses
                        )
                    ) ?>

                </span>


                <div class="status-items">

                    <?= htmlspecialchars(
                        implode(
                            ' | ',
                            $itemCodes
                        )
                    ) ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</div>

                                           


                                    <?php endif; ?>


                                </td>


                            <?php endforeach; ?>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>

            </div>

            <?php endif; ?>


        <?php endif; ?>

    </div>


</div>

<div
    class="modal-overlay status-timeline-overlay"
    id="statusTimelineModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="statusTimelineTitle"
    aria-hidden="true"
>
    <div class="modal status-timeline-modal">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="statusTimelineTitle">
                    Job Activity Timeline
                </h3>
                <div class="status-timeline-purchaser" id="statusTimelinePurchaser"></div>
            </div>

            <button
                type="button"
                class="modal-close"
                id="statusTimelineClose"
                aria-label="Close timeline"
            >
                &times;
            </button>
        </div>

        <div class="modal-body">
            <div id="statusTimelineContent"></div>

            <div class="modal-actions">
                <a class="button button-secondary" id="statusTimelineJobLink" href="#">
                    Open Full Job
                </a>
                <button type="button" class="button button-primary" id="statusTimelineDone">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="statusTimelineData"><?= json_encode(
    $jobTimelines,
    JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
) ?></script>

<script>

const statusSearch =
    document.getElementById('jobSearch');


if (statusSearch) {

    statusSearch.addEventListener(
        'input',
        function () {

            const searchText =
                statusSearch.value
                    .trim()
                    .toLowerCase();

            const searchWords =
                searchText
                    .split(/\s+/)
                    .filter(
                        word => word.length > 0
                    );


            const rows =
                document.querySelectorAll(
                    '.status-job-row'
                );


            rows.forEach(
                function (row) {

                    const searchableText =
                        (
                            row.dataset.search
                            || ''
                        ).toLowerCase();


                    const matches =
                        searchWords.every(
                            function (word) {

                                return searchableText
                                    .includes(word);

                            }
                        );


                    row.style.display =
                        (
                            searchWords.length === 0
                            ||
                            matches
                        )
                            ? ''
                            : 'none';

                }
            );

        }
    );

}

const timelineModal = document.getElementById('statusTimelineModal');
const timelineContent = document.getElementById('statusTimelineContent');
const timelineTitle = document.getElementById('statusTimelineTitle');
const timelinePurchaser = document.getElementById('statusTimelinePurchaser');
const timelineJobLink = document.getElementById('statusTimelineJobLink');
const timelineClose = document.getElementById('statusTimelineClose');
const timelineDone = document.getElementById('statusTimelineDone');
const timelineDataElement = document.getElementById('statusTimelineData');
const jobTimelines = timelineDataElement
    ? JSON.parse(timelineDataElement.textContent || '{}')
    : {};
let lastTimelineTrigger = null;

function timelineDateValue(dateText) {
    if (!dateText) {
        return null;
    }

    const parts = dateText.split('-').map(Number);
    return Date.UTC(parts[0], parts[1] - 1, parts[2]);
}

function timelineDateLabel(dateText) {
    if (!dateText) {
        return '—';
    }

    const parts = dateText.split('-');
    return parts[2] + '-' + parts[1] + '-' + parts[0];
}

function timelineDays(startDate, endDate) {
    const dayMilliseconds = 86400000;
    return Math.floor(
        (timelineDateValue(endDate) - timelineDateValue(startDate))
        / dayMilliseconds
    ) + 1;
}

function createTimelineRow(activity, rangeStart, rangeEnd, todayText) {
    const row = document.createElement('div');
    row.className = 'status-timeline-row';

    const heading = document.createElement('div');
    heading.className = 'status-timeline-row-heading';

    const name = document.createElement('strong');
    name.textContent = activity.name;
    heading.appendChild(name);

    const state = document.createElement('span');

    if (activity.completed_on) {
        state.className = 'status-timeline-state is-complete';
        state.textContent = 'Completed';
    } else if (activity.started_on) {
        state.className = 'status-timeline-state is-progress';
        state.textContent = 'In progress';
    } else {
        state.className = 'status-timeline-state is-pending';
        state.textContent = 'Not started';
    }

    heading.appendChild(state);
    row.appendChild(heading);

    const dates = document.createElement('div');
    dates.className = 'status-timeline-dates';

    if (activity.started_on) {
        const endDate = activity.completed_on || todayText;
        dates.textContent =
            'Started ' + timelineDateLabel(activity.started_on)
            + (activity.completed_on
                ? '  •  Completed ' + timelineDateLabel(activity.completed_on)
                : '  •  Ongoing')
            + '  •  ' + timelineDays(activity.started_on, endDate) + ' days';
    } else {
        dates.textContent = activity.completed_items + ' of '
            + activity.item_count + ' items completed';
    }

    row.appendChild(dates);

    const track = document.createElement('div');
    track.className = 'status-timeline-track';

    if (activity.started_on) {
        const start = timelineDateValue(activity.started_on);
        const end = timelineDateValue(activity.completed_on || todayText);
        const totalRange = Math.max(rangeEnd - rangeStart, 86400000);
        const left = ((start - rangeStart) / totalRange) * 100;
        const width = Math.max(((end - start) / totalRange) * 100, 2);
        const bar = document.createElement('div');

        bar.className = 'status-timeline-bar '
            + (activity.completed_on ? 'is-complete' : 'is-progress');
        bar.style.left = Math.max(0, left) + '%';
        bar.style.width = Math.min(100 - Math.max(0, left), width) + '%';
        track.appendChild(bar);
    }

    row.appendChild(track);
    return row;
}

function openJobTimeline(jobNumber, trigger) {
    const timeline = jobTimelines[jobNumber];

    if (!timeline || !timelineModal || !timelineContent) {
        return;
    }

    lastTimelineTrigger = trigger;
    timelineTitle.textContent = timeline.serial_no + ' Activity Timeline';
    timelinePurchaser.textContent = timeline.purchaser || '';
    timelineJobLink.href = timeline.job_url;
    timelineContent.replaceChildren();

    const today = new Date();
    const todayText = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0')
    ].join('-');
    const datedActivities = timeline.activities.filter(
        activity => activity.started_on
    );

    if (timeline.activities.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'empty status-timeline-empty';
        empty.textContent = 'No activities are configured for this job.';
        timelineContent.appendChild(empty);
    } else {
        const starts = datedActivities.map(
            activity => timelineDateValue(activity.started_on)
        );
        const ends = datedActivities.map(
            activity => timelineDateValue(activity.completed_on || todayText)
        );
        const rangeStart = starts.length ? Math.min(...starts) : timelineDateValue(todayText);
        const rangeEnd = ends.length ? Math.max(...ends) : timelineDateValue(todayText);

        timeline.activities.forEach(activity => {
            timelineContent.appendChild(
                createTimelineRow(activity, rangeStart, rangeEnd, todayText)
            );
        });
    }

    timelineModal.classList.add('show');
    timelineModal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    timelineClose.focus();
}

function closeJobTimeline() {
    if (!timelineModal) {
        return;
    }

    timelineModal.classList.remove('show');
    timelineModal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');

    if (lastTimelineTrigger) {
        lastTimelineTrigger.focus();
    }
}

document.querySelectorAll('.status-timeline-trigger').forEach(trigger => {
    trigger.addEventListener('click', function () {
        openJobTimeline(trigger.dataset.job, trigger);
    });
});

if (timelineClose) {
    timelineClose.addEventListener('click', closeJobTimeline);
    timelineDone.addEventListener('click', closeJobTimeline);

    timelineModal.addEventListener('click', function (event) {
        if (event.target === timelineModal) {
            closeJobTimeline();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && timelineModal.classList.contains('show')) {
            closeJobTimeline();
        }
    });
}

</script>

<?php require 'includes/footer.php'; ?>
