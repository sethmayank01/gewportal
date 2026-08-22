<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$config = require 'config.php';

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
| Get Job From ERP
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        serial_no,
        data
    FROM jobs
    WHERE serial_no = :serial_no
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    'serial_no' => $jobNo
]);

$job = $stmt->fetch();


if (!$job) {

    die("Job not found.");

}


/*
|--------------------------------------------------------------------------
| Decode Job JSON
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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars($jobNo) ?>
        - GEW Transformer Portal
    </title>
	
	<link rel="stylesheet" href="css/style.css">
 
</head>


<body>
<?php require 'includes/header.php'; ?>

<div class="container">


    <a class="back" href="jobs.php">

        ← Back to Jobs

    </a>
	<?php require 'includes/job_header.php'; ?>

   	        
    
    <!-- JOB DETAILS -->

    <div class="card">


        <h2 class="section-title">

            Job Details

        </h2>


        <div class="details">


 
            <div class="detail">

                <div class="detail-label">
                    Capacity
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['kva'] ?? ''
                    ) ?>

                    kVA

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Phase
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['phases'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    HV Voltage
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['hvVoltage'] ?? ''
                    ) ?>

                    V

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    LV Voltage
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['lvVoltage'] ?? ''
                    ) ?>

                    V

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Vector Group
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['vectorGroup'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Tapping Type
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['tappingType'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Tap Range
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['tappingRangeMin'] ?? ''
                    ) ?>

                    %

                    to

                    <?= htmlspecialchars(
                        $data['tappingRangeMax'] ?? ''
                    ) ?>

                    %

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Step Voltage
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['stepVoltage'] ?? ''
                    ) ?>

                    %

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Material
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['material'] ?? 'Not specified'
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Quantity
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['quantity'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Job Type
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['jobType'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Relevant IS
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['relevantIS'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Entry Date
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['entryDateTime'] ?? ''
                    ) ?>

                </div>

            </div>


            <div class="detail">

                <div class="detail-label">
                    Created By
                </div>

                <div class="detail-value">

                    <?= htmlspecialchars(
                        $data['createdBy'] ?? ''
                    ) ?>

                </div>

            </div>


        </div>

    </div>


    <!-- ENGINEERING MODULES -->

    <div class="card">


        <h2 class="section-title">

            Engineering

        </h2>


        <div class="module-grid">


            <div class="module">

                <h3>Drawings</h3>

                <p>

                    View and manage transformer
                    drawings and revisions.

                </p>

                <!-- We'll activate this next -->

                <a href="drawings.php?job=<?= urlencode($jobNo) ?>">
					Drawings →
				</a>

            </div>


            <div class="module">

                <h3>BOM</h3>

                <p>

                    View and modify the material
                    BOM for this transformer.

                </p>

                <!-- We'll activate this after drawings -->

                <a href="bom.php?job=<?= urlencode($jobNo) ?>">
					BOM →
				</a>

            </div>


            <div class="module">

                <h3>Inspection Documents</h3>

                <p>

                    Inspection Documents

                </p>

                 <a href="inspection.php?job=<?= urlencode($jobNo) ?>">
					Documents →
				</a>

            </div>
			
<div class="module">

    <h3>Production</h3>

    <p>
        Configure process items and record
        daily production progress.
    </p>

    <a href="daily_progress.php?job=<?= urlencode($jobNo) ?>">
        Daily Progress →
    </a>

    <br><br>

    <a href="job_process.php?job=<?= urlencode($jobNo) ?>">
        Process Setup →
    </a>

</div>



        </div>

    </div>


</div>
<?php require 'includes/footer.php'; ?>