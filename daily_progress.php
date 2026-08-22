<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';
require 'includes/general_config.php';


/*
|--------------------------------------------------------------------------
| Get Job
|--------------------------------------------------------------------------
*/

$jobNo = trim(
    $_GET['job']
    ?? $_POST['job']
    ?? ''
);

if ($jobNo === '') {
    http_response_code(400);
    exit('Invalid job number.');
}


/*
|--------------------------------------------------------------------------
| Verify Job Exists
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        serial_no,
        data
    FROM jobs
    WHERE serial_no = :serial_no
    LIMIT 1
");

$stmt->execute([
    'serial_no' => $jobNo
]);

$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    exit('Job not found.');
}


/*
|--------------------------------------------------------------------------
| Decode Job Data
|--------------------------------------------------------------------------
*/

$data = [];

if (!empty($job['data'])) {

    $decoded = json_decode(
        $job['data'],
        true
    );

    if (is_array($decoded)) {
        $data = $decoded;
    }

}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$currentUser =
    $_SESSION['user']['username']
    ?? '';


/*
|--------------------------------------------------------------------------
| Progress Date
|--------------------------------------------------------------------------
*/

$progressDate =
    $_POST['progress_date']
    ?? $_GET['date']
    ?? date('Y-m-d');


$dateObject =
    DateTime::createFromFormat(
        'Y-m-d',
        $progressDate
    );

if (
    !$dateObject
    ||
    $dateObject->format('Y-m-d')
        !== $progressDate
) {

    $progressDate =
        date('Y-m-d');

}


/*
|--------------------------------------------------------------------------
| Selected Main Activity
|--------------------------------------------------------------------------
|
| Only the selected activity is rendered on the page.
|
*/

$selectedProcess =
    trim(
        $_GET['activity']
        ?? $_POST['activity']
        ?? ''
    );

if (
    $selectedProcess !== ''
    &&
    !isset(
        $processDefinitions[
            $selectedProcess
        ]
    )
) {

    $selectedProcess = '';

}


/*
|--------------------------------------------------------------------------
| Save Daily Progress
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action']
        ?? '';

    if ($action === 'save') {

        $statusByItem =
            $_POST['status']
            ?? [];
		
		$plannedStatusByItem =
    $_POST['planned_status']
    ?? [];

        $remarksByItem =
            $_POST['remarks']
            ?? [];


        if (!is_array($statusByItem)) {
            $statusByItem = [];
        }

        if (!is_array($remarksByItem)) {
            $remarksByItem = [];
        }
		
		if (!is_array($plannedStatusByItem)) {
    $plannedStatusByItem = [];
}


        /*
        |--------------------------------------------------------------------------
        | Get Active Items For This Job
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                process_code,
                item_code,
                item_name
            FROM job_process_items
            WHERE job_serial_no = :job_serial_no
              AND active = TRUE
            ORDER BY
                process_code,
                item_code
        ");

        $stmt->execute([
            'job_serial_no' => $jobNo
        ]);

        $activeItems =
            $stmt->fetchAll();


        try {

            $pdo->beginTransaction();


            $upsert =
    $pdo->prepare("
        INSERT INTO job_process_daily_status
        (
            job_process_item_id,
            progress_date,
            status_code,
            planned_status_code,
            remarks,
            updated_by,
            updated_at
        )
        VALUES
        (
            :item_id,
            :progress_date,
            :status_code,
            :planned_status_code,
            :remarks,
            :updated_by,
            CURRENT_TIMESTAMP
        )
        ON CONFLICT
        (
            job_process_item_id,
            progress_date
        )
        DO UPDATE SET

            status_code =
                EXCLUDED.status_code,

            planned_status_code =
                EXCLUDED.planned_status_code,

            remarks =
                EXCLUDED.remarks,

            updated_by =
                EXCLUDED.updated_by,

            updated_at =
                CURRENT_TIMESTAMP
    ");


            foreach (
                $activeItems
                as $item
            ) {

                $itemId =
                    (int)$item['id'];

                $statusCode =
                    trim(
                        $statusByItem[
                            $itemId
                        ]
                        ?? ''
                    );
				
				$plannedStatusCode =
    trim(
        $plannedStatusByItem[
            $itemId
        ]
        ?? ''
    );
				
                $remarks =
                    trim(
                        $remarksByItem[
                            $itemId
                        ]
                        ?? ''
                    );


                /*
                |--------------------------------------------------------------------------
                | Blank status = do not create/update a record
                |--------------------------------------------------------------------------
                */

                if (
    $statusCode === ''
    &&
    $plannedStatusCode === ''
) {
    continue;
}


                /*
                |--------------------------------------------------------------------------
                | Validate Status Against General Config
                |--------------------------------------------------------------------------
                */

                if (
    $statusCode !== ''
    &&
    !isset(
        $processStatuses[
            $statusCode
        ]
    )
) {
    throw new RuntimeException(
        'Invalid current status selected for item: '
        . $item['item_name']
    );
}	

if (
    $plannedStatusCode !== ''
    &&
    !isset(
        $processStatuses[
            $plannedStatusCode
        ]
    )
) {
    throw new RuntimeException(
        'Invalid planned status selected for item: '
        . $item['item_name']
    );
}


               $upsert->execute([

    'item_id' =>
        $itemId,

    'progress_date' =>
        $progressDate,

    'status_code' =>
        $statusCode === ''
            ? null
            : $statusCode,

    'planned_status_code' =>
        $plannedStatusCode === ''
            ? null
            : $plannedStatusCode,

    'remarks' =>
        $remarks === ''
            ? null
            : $remarks,

    'updated_by' =>
        $currentUser

]);

            }


            $pdo->commit();


            $redirectUrl =
                'daily_progress.php?job=' .
                urlencode($jobNo) .
                '&date=' .
                urlencode($progressDate) .
                '&saved=1';

            if ($selectedProcess !== '') {

                $redirectUrl .=
                    '&activity=' .
                    urlencode($selectedProcess);

            }

            header(
                'Location: ' .
                $redirectUrl
            );

            exit;


        } catch (
            Throwable $e
        ) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();

            }


            $error =
                'Could not save daily progress: '
                . $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| Get Active Process Items
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        process_code,
        item_code,
        item_name,
        active
    FROM job_process_items
    WHERE job_serial_no = :job_serial_no
      AND active = TRUE
    ORDER BY
        process_code,
        item_code
");

$stmt->execute([
    'job_serial_no' =>
        $jobNo
]);

$processItems =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Daily Status For Selected Date
|--------------------------------------------------------------------------
*/

$dailyStatus = [];

if (!empty($processItems)) {

    $itemIds =
        array_map(
            static function ($item) {
                return (int)$item['id'];
            },
            $processItems
        );


    $placeholders = [];
    $params = [
        ':progress_date' =>
            $progressDate
    ];


    foreach (
        $itemIds
        as $index => $itemId
    ) {

        $key =
            ':item' . $index;

        $placeholders[] =
            $key;

        $params[$key] =
            $itemId;

    }


    $sql = "
        SELECT
    job_process_item_id,
    status_code,
    planned_status_code,
    remarks,
    updated_by,
    updated_at
        FROM job_process_daily_status
        WHERE progress_date = :progress_date
          AND job_process_item_id IN (
              " .
              implode(
                  ',',
                  $placeholders
              ) .
              "
          )
    ";


    $stmt =
        $pdo->prepare($sql);

    $stmt->execute(
        $params
    );


    foreach (
        $stmt->fetchAll()
        as $row
    ) {

        $dailyStatus[
            (int)$row[
                'job_process_item_id'
            ]
        ] = $row;

    }

}


/*
|--------------------------------------------------------------------------
| Group Items By Process
|--------------------------------------------------------------------------
*/

$itemsByProcess = [];

foreach (
    $processItems
    as $item
) {

    $processCode =
        $item['process_code'];

    if (
        !isset(
            $itemsByProcess[
                $processCode
            ]
        )
    ) {

        $itemsByProcess[
            $processCode
        ] = [];

    }

    $itemsByProcess[
        $processCode
    ][] = $item;

}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Daily Progress - ' .
    $jobNo;

require 'includes/header.php';

?>


<div class="container">


    <a
        class="back"
        href="job.php?job=<?= urlencode($jobNo) ?>"
    >
        ← Back to Job
    </a>


    <?php require 'includes/job_header.php'; ?>


    <?php if (!empty($error)): ?>

        <div class="error-box">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <?php if (isset($_GET['saved'])): ?>

        <div class="success-box">

            Daily progress saved successfully.

        </div>

    <?php endif; ?>


    <!-- =====================================================
         DATE
         ===================================================== -->

   
    <!-- =====================================================
         PROGRESS
         ===================================================== -->

    <?php if (empty($processItems)): ?>
		
		 <div class="card">
        No active process items...
    </div>
       
    <?php else: ?>

        <!-- Activity selector -->

     <div class="card">

    <div class="progress-header">

        <div class="progress-heading">

            <h2 class="card-title">
                Daily Production Progress
            </h2>

            <div class="muted">
                Select the date and main activity for which
                progress is being recorded.
            </div>

        </div>


        <div class="progress-controls">

            <div class="progress-date-area">

                <label
                    class="form-label"
                    for="progressDate"
                >
                    Progress Date
                </label>

                <form
                    method="GET"
                    class="progress-date-form"
                >

                    <input
                        type="hidden"
                        name="job"
                        value="<?= htmlspecialchars($jobNo) ?>"
                    >

                    <?php if ($selectedProcess !== ''): ?>

                        <input
                            type="hidden"
                            name="activity"
                            value="<?= htmlspecialchars(
                                $selectedProcess
                            ) ?>"
                        >

                    <?php endif; ?>

                    <input
                        type="date"
                        id="progressDate"
                        name="date"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $progressDate
                        ) ?>"
                        onchange="this.form.submit()"
                    >

                </form>

            </div>


            <div class="progress-activity">

                <label
                    class="form-label"
                    for="processFilter"
                >
                    Main Activity
                </label>

                <form
                    method="GET"
                    class="progress-activity-form"
                >

                    <input
                        type="hidden"
                        name="job"
                        value="<?= htmlspecialchars($jobNo) ?>"
                    >

                    <input
                        type="hidden"
                        name="date"
                        value="<?= htmlspecialchars(
                            $progressDate
                        ) ?>"
                    >

                    <select
                        id="processFilter"
                        name="activity"
                        class="form-control progress-activity-select"
                        onchange="this.form.submit()"
                    >

                        <option value="">
                            Select Activity
                        </option>

                        <?php foreach (
                            $itemsByProcess
                            as $processCode => $items
                        ): ?>

                            <?php

                            $processName =
                                $processDefinitions[
                                    $processCode
                                ]['name']
                                ??
                                $processCode;

                            ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $processCode
                                ) ?>"
                                <?= $selectedProcess === $processCode
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $processName
                                ) ?>

                                (<?= count($items) ?>)

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>

            </div>

        </div>

    </div>

</div>


        <!-- Daily progress form -->

        <form
            method="POST"
            id="dailyProgressForm"
        >

            <input
                type="hidden"
                name="job"
                value="<?= htmlspecialchars($jobNo) ?>"
            >

            <input
                type="hidden"
                name="activity"
                value="<?= htmlspecialchars($selectedProcess) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="save"
            >

            <input
                type="hidden"
                name="progress_date"
                id="progressDateHidden"
                value="<?= htmlspecialchars($progressDate) ?>"
            >


            <?php foreach (
                $itemsByProcess
                as $processCode => $items
            ): ?>

                <?php

                if (
                    $selectedProcess === ''
                    ||
                    $selectedProcess !== $processCode
                ) {
                    continue;
                }

                $processName =
                    $processDefinitions[
                        $processCode
                    ]['name']
                    ??
                    $processCode;

                ?>

                <div class="card">

                    <div class="section-heading-row">

                        <h2 class="section-heading">
                            <?= htmlspecialchars(
                                $processName
                            ) ?>
                        </h2>

                        <div class="muted">
                            <?= count($items) ?>
                            item(s)
                        </div>

                    </div>


                    <div class="table-container">

                        <table class="data-table">

                            <thead>

                                <tr>

                                    <th>Item Code</th>
                                    <th>Item</th>
                                    <th>Today's Status</th>
									<th>Next Day Plan</th>
                                    <th>Remarks</th>
                                    <th>Last Updated</th>

                                </tr>

                            </thead>


                            <tbody>

                            <?php foreach ($items as $item): ?>

                                <?php

                                $itemId =
                                    (int)$item['id'];

                                $existing =
                                    $dailyStatus[
                                        $itemId
                                    ] ?? null;

                                $existingStatus =
                                    $existing[
                                        'status_code'
                                    ] ?? '';

                                $existingRemarks =
                                    $existing[
                                        'remarks'
                                    ] ?? '';
									
								$existingPlannedStatus =
    $existing[
        'planned_status_code'
    ] ?? '';

                                ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $item['item_code']
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['item_name']
                                        ) ?>
                                    </td>

                                    <td>

                                        <select
                                            name="status[<?= $itemId ?>]"
                                            class="form-control progress-status"
                                        >

                                            <option value="">
                                                —
                                            </option>

                                            <?php foreach (
                                                $processStatuses
                                                as $statusCode => $status
                                            ): ?>

                                                <option
                                                    value="<?= htmlspecialchars(
                                                        $statusCode
                                                    ) ?>"
                                                    <?= $existingStatus === $statusCode
                                                        ? 'selected'
                                                        : ''
                                                    ?>
                                                >
                                                    <?= htmlspecialchars(
                                                        $status['name']
                                                    ) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>

                                    </td>
									<td>

    <select
        name="planned_status[<?= $itemId ?>]"
        class="form-control progress-status"
    >

        <option value="">
            —
        </option>

        <?php foreach (
            $processStatuses
            as $statusCode => $status
        ): ?>

            <option
                value="<?= htmlspecialchars(
                    $statusCode
                ) ?>"
                <?= $existingPlannedStatus === $statusCode
                    ? 'selected'
                    : ''
                ?>
            >

                <?= htmlspecialchars(
                    $status['name']
                ) ?>

            </option>

        <?php endforeach; ?>

    </select>

</td>

                                    <td>

                                        <input
                                            type="text"
                                            name="remarks[<?= $itemId ?>]"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                $existingRemarks
                                            ) ?>"
                                            placeholder="Optional"
                                        >

                                    </td>

                                    <td>

                                        <?php if ($existing): ?>

                                            <div class="small">

                                                <?= htmlspecialchars(
                                                    $existing['updated_by']
                                                    ?? ''
                                                ) ?>

                                                <br>

                                                <?= htmlspecialchars(
                                                    date(
                                                        'd-M-Y h:i A',
                                                        strtotime(
                                                            $existing['updated_at']
                                                        )
                                                    )
                                                ) ?>

                                            </div>

                                        <?php else: ?>

                                            —

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </div>

            <?php endforeach; ?>


            <div class="page-actions progress-actions">

                <div class="muted">

                    Changes are saved for:

                    <strong>
                        <?= htmlspecialchars(
                            date(
                                'd-M-Y',
                                strtotime($progressDate)
                            )
                        ) ?>
                    </strong>

                </div>

                <button
                    type="submit"
                    class="button button-primary button-large"
                >
                    Save Daily Progress
                </button>

            </div>

        </form>




    <?php endif; ?>

</div>


<?php require 'includes/footer.php'; ?>