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
| TRFD = New Transformer
| TRFR = Repair
| ALL  = All Jobs
|
*/

$jobType = $_GET['type'] ?? 'TRFD';

if (!in_array($jobType, ['TRFD', 'TRFR', 'ALL'], true)) {
    $jobType = 'TRFD';
}



/*
|--------------------------------------------------------------------------
| Get Jobs
|--------------------------------------------------------------------------
*/

$where = [];
$params = [];

if ($jobType === 'TRFD') {

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
    array_keys(
        $processDefinitions
    );


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


            <div class="status-table-container">

                <table class="status-table">


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


                            <td>

                                <a
                                    class="job-link"
                                    href="job.php?job=<?= urlencode(
                                        $job['serial_no']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $job['serial_no']
                                    ) ?>

                                </a>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $jobData[
                                        'purchaserName'
                                    ] ?? ''
                                ) ?>

                            </td>


                            <td>

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
                                >


                                    <?php

                                    $processItems =
                                        $job[
                                            'processes'
                                        ][$processCode]
                                        ?? [];

                                    ?>


                                    <?php if (
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

    </div>


</div>

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

</script>

<?php require 'includes/footer.php'; ?>
