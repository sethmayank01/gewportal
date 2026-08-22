<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Get Job Number
|--------------------------------------------------------------------------
*/

$jobNo = trim($_GET['job'] ?? '');

if ($jobNo === '') {
    header('Location: jobs.php');
    exit;
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
    die('Job not found.');
}


/*
|--------------------------------------------------------------------------
| Get Existing Drawings
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        d.id,
        d.section,
        d.drawing_no,
        d.title,
        d.current_revision,
        d.created_by,
        d.created_at,

        dr.id AS current_revision_id

    FROM drawings d

    LEFT JOIN drawing_revision dr
        ON dr.drawing_id = d.id
        AND dr.revision = d.current_revision

    WHERE d.job_serial_no = :job

    ORDER BY d.section, d.drawing_no
");

$stmt->execute([
    'job' => $jobNo
]);

$drawings = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Decode Job Data
|--------------------------------------------------------------------------
*/

$data = json_decode(
    $job['data'],
    true
);

if (!is_array($data)) {
    $data = [];
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Drawings - <?= htmlspecialchars($jobNo) ?>
    </title>
	<?php require 'includes/header.php'; ?>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>


<body>


<!-- =========================================================
     COMMON PORTAL HEADER
     ========================================================= -->
   <!-- =====================================================
         BACK TO JOB
         ===================================================== -->

   
<div class="container">
 <a
        class="back"
        href="job.php?job=<?= urlencode($jobNo) ?>"
    >

        ← Back to Job

    </a>

<?php require 'includes/job_header.php'; ?>
 


       <!-- =====================================================
         DRAWINGS
         ===================================================== -->

    <div class="card">


        <div class="section-heading-row">

            <h2 class="section-heading">

                Drawings

            </h2>


            <a
                class="button button-primary"
                href="add_drawing.php?job=<?= urlencode($jobNo) ?>"
            >

                + Add Drawing

            </a>

        </div>


        <?php if (count($drawings) === 0): ?>


            <div class="empty">

                No drawings have been uploaded
                for this job.

            </div>


        <?php else: ?>


            <div class="table-container">

                <table class="drawing-table">

                    <thead>

                        <tr>

                            <th>
                                Section
                            </th>

                            <th>
                                Drawing No.
                            </th>

                            <th>
                                Title
                            </th>

                            <th>
                                Current Revision
                            </th>

                            <th>
                                Created By
                            </th>

                            <th>
                                Created On
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $drawings
                        as $drawing
                    ): ?>


                        <tr>


                            <td>

                                <?= htmlspecialchars(
                                    $drawing['section']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $drawing['drawing_no']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $drawing['title']
                                ) ?>

                            </td>


                            <td>


                                <?php if (
                                    !empty(
                                        $drawing['current_revision']
                                    )
                                    &&
                                    !empty(
                                        $drawing[
                                            'current_revision_id'
                                        ]
                                    )
                                ): ?>


                                    <a
                                        class="revision"
                                        href="drawing.php?id=<?= (int)$drawing['id'] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $drawing[
                                                'current_revision'
                                            ]
                                        ) ?>

                                    </a>


                                <?php else: ?>


                                    <span class="no-drawing">

                                        No Revision

                                    </span>


                                <?php endif; ?>


                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $drawing['created_by']
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    date(
                                        'd-M-Y h:i A',
                                        strtotime(
                                            $drawing['created_at']
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>


                                <?php if (
                                    !empty(
                                        $drawing[
                                            'current_revision_id'
                                        ]
                                    )
                                ): ?>


                                    <a
                                        class="button button-primary"
                                        href="view_revision.php?id=<?= (int)$drawing['current_revision_id'] ?>"
                                        target="_blank"
                                    >

                                        View Drawing

                                    </a>


                                <?php else: ?>


                                    <span class="no-drawing">

                                        No PDF

                                    </span>


                                <?php endif; ?>


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
