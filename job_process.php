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

$jobNo = trim($_GET['job'] ?? $_POST['job'] ?? '');

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
| Current User
|--------------------------------------------------------------------------
*/

$currentUser =
    $_SESSION['user']['username']
    ?? '';


/*
|--------------------------------------------------------------------------
| Seed Default Process Activities
|--------------------------------------------------------------------------
|
| Jobs are supplied by the jobs table rather than created in this page. On
| the first Process Setup visit for a job with no activities, create the
| standard activity list. A lock on the job row prevents two simultaneous
| first visits from creating duplicate rows.
|
*/

try {

    $pdo->beginTransaction();

    $lockJob = $pdo->prepare("\n        SELECT serial_no\n        FROM jobs\n        WHERE serial_no = :serial_no\n        FOR UPDATE\n    ");

    $lockJob->execute([
        'serial_no' => $jobNo
    ]);

    $existingCount = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM job_process_items\n        WHERE job_serial_no = :job_serial_no\n    ");

    $existingCount->execute([
        'job_serial_no' => $jobNo
    ]);

    if ((int)$existingCount->fetchColumn() === 0) {

        $insertDefault = $pdo->prepare("\n            INSERT INTO job_process_items\n            (\n                job_serial_no,\n                process_code,\n                item_code,\n                item_name,\n                active,\n                created_by,\n                created_at,\n                updated_by,\n                updated_at\n            )\n            VALUES\n            (\n                :job_serial_no,\n                :process_code,\n                :item_code,\n                :item_name,\n                TRUE,\n                :created_by,\n                CURRENT_TIMESTAMP,\n                :updated_by,\n                CURRENT_TIMESTAMP\n            )\n        ");

        foreach ($defaultProcessItems as $defaultItem) {
            $insertDefault->execute([
                'job_serial_no' => $jobNo,
                'process_code'  => $defaultItem['process_code'],
                'item_code'     => $defaultItem['item_code'],
                'item_name'     => $defaultItem['item_name'],
                'created_by'    => $currentUser,
                'updated_by'    => $currentUser
            ]);
        }

    }

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Unable to seed default process activities for job '
        . $jobNo
        . ': '
        . $e->getMessage()
    );

    http_response_code(500);
    exit('Unable to initialize the default process activities.');

}


/*
|--------------------------------------------------------------------------
| Process Definitions
|--------------------------------------------------------------------------
|
| The user creates job-specific items under these processes.
|
*/

$processOptions = $processDefinitions;


/*
|--------------------------------------------------------------------------
| Save New Process Item
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action =
        $_POST['action'] ?? '';

    if ($action === 'add') {

        $processCode =
            trim(
                $_POST['process_code'] ?? ''
            );

        $itemCode =
            strtoupper(
                trim(
                    $_POST['item_code'] ?? ''
                )
            );

        $itemName =
            trim(
                $_POST['item_name'] ?? ''
            );

       

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            !isset(
                $processDefinitions[
                    $processCode
                ]
            )
        ) {

            $error =
                'Please select a valid process.';

        }
        elseif (
            $itemCode === ''
        ) {

            $error =
                'Item code is required.';

        }
        elseif (
            $itemName === ''
        ) {

            $error =
                'Item name is required.';

        }
       
        else {

            try {

                $stmt =
                    $pdo->prepare("
                        INSERT INTO job_process_items
                        (
                            job_serial_no,
                            process_code,
                            item_code,
                            item_name,
                            active,
                            created_by,
                            created_at,
                            updated_by,
                            updated_at
                        )
                        VALUES
                        (
                            :job_serial_no,
                            :process_code,
                            :item_code,
                            :item_name,
                            TRUE,
                            :created_by,
                            CURRENT_TIMESTAMP,
                            :updated_by,
                            CURRENT_TIMESTAMP
                        )
                    ");

                $stmt->execute([

                    'job_serial_no' =>
                        $jobNo,

                    'process_code' =>
                        $processCode,

                    'item_code' =>
                        $itemCode,

                    'item_name' =>
                        $itemName,

                    'created_by' =>
                        $currentUser,

                    'updated_by' =>
                        $currentUser

                ]);


                header(
                    'Location: job_process.php?job=' .
                    urlencode($jobNo)
                );

                exit;


            }
            catch (
                PDOException $e
            ) {

                if (
                    $e->getCode() === '23505'
                ) {

                    $error =
                        'This process item already exists for this job.';

                }
                else {

                    $error =
                        'Could not add process item: ' .
                        $e->getMessage();

                }

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Deactivate Process Item
    |--------------------------------------------------------------------------
    */

    if ($action === 'deactivate') {

        $itemId =
            (int)(
                $_POST['item_id']
                ?? 0
            );

        if ($itemId <= 0) {

            $error =
                'Invalid process item.';

        }
        else {

            $stmt =
                $pdo->prepare("
                    UPDATE job_process_items
                    SET
                        active = FALSE,
                        updated_by = :updated_by,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                      AND job_serial_no = :job_serial_no
                ");

            $stmt->execute([

                'updated_by' =>
                    $currentUser,

                'id' =>
                    $itemId,

                'job_serial_no' =>
                    $jobNo

            ]);


            header(
                'Location: job_process.php?job=' .
                urlencode($jobNo)
            );

            exit;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Reactivate Process Item
    |--------------------------------------------------------------------------
    */

    if ($action === 'activate') {

        $itemId =
            (int)(
                $_POST['item_id']
                ?? 0
            );

        if ($itemId <= 0) {

            $error =
                'Invalid process item.';

        }
        else {

            $stmt =
                $pdo->prepare("
                    UPDATE job_process_items
                    SET
                        active = TRUE,
                        updated_by = :updated_by,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                      AND job_serial_no = :job_serial_no
                ");

            $stmt->execute([

                'updated_by' =>
                    $currentUser,

                'id' =>
                    $itemId,

                'job_serial_no' =>
                    $jobNo

            ]);


            header(
                'Location: job_process.php?job=' .
                urlencode($jobNo)
            );

            exit;

        }

    }

}


/*
|--------------------------------------------------------------------------
| Get Existing Process Items
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("
        SELECT
            id,
            process_code,
            item_code,
            item_name,
            active,
            created_by,
            created_at,
            updated_by,
            updated_at
        FROM job_process_items
        WHERE job_serial_no = :job_serial_no
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
| Job Data
|--------------------------------------------------------------------------
*/

$data = [];

if (!empty($job['data'])) {

    $decoded =
        json_decode(
            $job['data'],
            true
        );

    if (is_array($decoded)) {
        $data = $decoded;
    }

}


/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Process Setup - ' .
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


    <!-- =====================================================
         ADD PROCESS ITEM
         ===================================================== -->

    <div class="card">

        <h2 class="card-title">
            Add Process Item
        </h2>


        <form
            method="POST"
            class="process-item-form"
        >

            <input
                type="hidden"
                name="job"
                value="<?= htmlspecialchars($jobNo) ?>"
            >

            <input
                type="hidden"
                name="action"
                value="add"
            >


            <div class="form-grid-4">


                <div class="form-group">

                    <label class="form-label">
                        Process
                    </label>

                    <select
                        name="process_code"
                        id="processCode"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Process
                        </option>


                        <?php foreach (
                            $processOptions
                            as $code => $process
                        ): ?>

                            <option
                                value="<?= htmlspecialchars($code) ?>"
                                data-process-name="<?= htmlspecialchars(
                                    $process['name']
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $process['name']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Item Code
                    </label>

                    <input
                        type="text"
                        name="item_code"
                        class="form-control"
                        placeholder="e.g. LV1"
                        required
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Item Name
                    </label>

                    <input
                        type="text"
                        name="item_name"
                        class="form-control"
                        placeholder="e.g. LV Winding 1"
                        required
                    >

                </div>


                <div class="form-group">

                    <label class="form-label">
                        Sequence
                    </label>

                    <input
                        type="number"
                        name="sequence_no"
                        class="form-control"
                        min="1"
                        value="1"
                        required
                    >

                </div>


            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    class="button button-primary"
                >
                    Add Process Item
                </button>

            </div>


        </form>

    </div>


    <!-- =====================================================
         CURRENT PROCESS ITEMS
         ===================================================== -->

    <div class="card">

        <div class="section-heading-row">

            <h2 class="section-heading">
                Process Items
            </h2>


            <div class="muted">

                <?= count($processItems) ?>
                item(s)

            </div>

        </div>


        <?php if (empty($processItems)): ?>


            <div class="empty">

                No process items have been defined
                for this job.

            </div>


        <?php else: ?>


            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>
                                Process
                            </th>

                            <th>
                                Item Code
                            </th>

                            <th>
                                Item Name
                            </th>

                            
                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $processItems
                        as $item
                    ): ?>


                        <tr>


                            <td>

                                <?= htmlspecialchars(
                                    $processDefinitions[
                                        $item['process_code']
                                    ]['name']
                                    ??
                                    $item['process_code']
                                ) ?>

                            </td>


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


                                <?php if (
                                    $item['active']
                                ): ?>

                                    <span
                                        class="
                                            status
                                            status-success
                                        "
                                    >
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="
                                            status
                                            status-missing
                                        "
                                    >
                                        Inactive
                                    </span>

                                <?php endif; ?>


                            </td>


                            <td>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                >

                                    <input
                                        type="hidden"
                                        name="job"
                                        value="<?= htmlspecialchars($jobNo) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int)$item['id'] ?>"
                                    >


                                    <?php if (
                                        $item['active']
                                    ): ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                                button
                                                button-danger
                                                button-small
                                            "
                                            onclick="
                                                return confirm(
                                                    'Deactivate this process item?'
                                                );
                                            "
                                        >
                                            Deactivate
                                        </button>


                                    <?php else: ?>


                                        <input
                                            type="hidden"
                                            name="action"
                                            value="activate"
                                        >

                                        <button
                                            type="submit"
                                            class="
                                                button
                                                button-success
                                                button-small
                                            "
                                        >
                                            Activate
                                        </button>


                                    <?php endif; ?>


                                </form>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>


</div>


<?php require 'includes/footer.php'; ?>
