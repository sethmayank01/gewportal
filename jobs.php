<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$config = require 'config.php';

require 'db.php';

require 'includes/general_config.php';

/*
|--------------------------------------------------------------------------
| Job Type Filter
|--------------------------------------------------------------------------
|
| TRFD = New Transformer
| TRFR = Repair
|
*/

$jobType = $_GET['type'] ?? 'TRFD';

if (!in_array($jobType, ['TRFD', 'TRFR', 'ALL'])) {
    $jobType = 'TRFD';
}


/*
|--------------------------------------------------------------------------
| Get Jobs
|--------------------------------------------------------------------------
*/

if ($jobType === 'TRFD') {

    $sql = "
        SELECT
            serial_no,
            data
        FROM jobs
        WHERE serial_no LIKE 'TRFD%'
        ORDER BY serial_no DESC
    ";

    $stmt = $pdo->query($sql);

}
elseif ($jobType === 'TRFR') {

    $sql = "
        SELECT
            serial_no,
            data
        FROM jobs
        WHERE serial_no LIKE 'TRFR%'
        ORDER BY serial_no DESC
    ";

    $stmt = $pdo->query($sql);

}
else {

    $sql = "
        SELECT
            serial_no,
            data
        FROM jobs
        WHERE serial_no LIKE 'TRFD%'
           OR serial_no LIKE 'TRFR%'
        ORDER BY serial_no DESC
    ";

    $stmt = $pdo->query($sql);

}

$jobs = $stmt->fetchAll();

/*
|--------------------------------------------------------------------------
| Get Actual Drawings Available For Each Job
|--------------------------------------------------------------------------
*/

$drawingMap = [];

if (!empty($jobs)) {

    $jobSerials = array_column(
        $jobs,
        'serial_no'
    );

    $placeholders = [];

    $params = [];

    foreach (
        $jobSerials
        as $i => $serialNo
    ) {

        $key = ':job' . $i;

        $placeholders[] = $key;

        $params[$key] = $serialNo;

    }


    $drawingSql = "
        SELECT
            job_serial_no,
            section
        FROM drawings
        WHERE job_serial_no IN (
            " . implode(',', $placeholders) . "
        )
        ORDER BY job_serial_no, id
    ";


    $drawingStmt =
        $pdo->prepare($drawingSql);

    $drawingStmt->execute(
        $params
    );


    $drawingRows =
        $drawingStmt->fetchAll();


    foreach (
        $drawingRows
        as $drawingRow
    ) {

        $jobSerial =
            $drawingRow['job_serial_no'];

        $section =
            $drawingRow['section'];


        if (!isset($drawingMap[$jobSerial])) {

            $drawingMap[$jobSerial] = [];

        }


        $drawingMap[$jobSerial][] =
            $section;

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>GEW Transformer Portal - Jobs</title>
	<link rel="stylesheet" href="css/style.css">
   
</head>
<?php require 'includes/header.php'; ?>


<body>


<div class="container">

    <!-- Filter -->
	<div class="card">

    <div class="jobs-toolbar">

        <div class="filter-row">

            <label>
                Job Type:
            </label>

            <form method="GET">

                <select
                    name="type"
                    onchange="this.form.submit()"
                >

                    <option
                        value="TRFD"
                        <?= $jobType === 'TRFD' ? 'selected' : '' ?>
                    >
                        New Transformer
                    </option>

                    <option
                        value="TRFR"
                        <?= $jobType === 'TRFR' ? 'selected' : '' ?>
                    >
                        Repair
                    </option>

                    <option
                        value="ALL"
                        <?= $jobType === 'ALL' ? 'selected' : '' ?>
                    >
                        All
                    </option>

                </select>

            </form>

        </div>


        <a
            href="status.php"
            class="button button-primary"
        >
            Production Status →
        </a>

    </div>

</div>
    
    <!-- Jobs -->

    <div class="card">

        <h2>
            <?= $jobType === 'TRFD'
                ? 'New Transformer Jobs'
                : (
                    $jobType === 'TRFR'
                        ? 'Repair Jobs'
                        : 'All Transformer Jobs'
                )
            ?>
        </h2>


        <?php if (count($jobs) === 0): ?>

            <div class="empty">
                No jobs found.
            </div>

        <?php else: ?>

            <div class="table-container">

                <table class="data-table">

                    <thead>

                        <tr>

                            <th>Job No.</th>
                            <th>Purchaser</th>
                            <th>kVA</th>
                            <th>Phase</th>
                            <th>HV Voltage</th>
                            <th>LV Voltage</th>
                            <th>Vector Group</th>
                            <th>Quantity</th>
                            <th>Job Type</th>
							<th>Drawings</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php foreach ($jobs as $job): ?>

    <?php

    $data = json_decode(
        $job['data'],
        true
    );

    if (!is_array($data)) {
        $data = [];
    }


    /*
    |--------------------------------------------------------------------------
    | Drawing Codes Available For This Job
    |--------------------------------------------------------------------------
    */

    $jobDrawingSections =
        $drawingMap[
            $job['serial_no']
        ] ?? [];


    $drawingCodes = [];


    /*
    | Follow the master $sections order
    | rather than database order.
    */

    foreach (
        $sections
        as $section
    ) {

        if (
            in_array(
                $section,
                $jobDrawingSections,
                true
            )
            &&
            isset(
                $sectionDefaults[
                    $section
                ]['code']
            )
        ) {

            $drawingCodes[] =
                $sectionDefaults[
                    $section
                ]['code'];

        }

    }

    ?>

                        <tr>

                            <td>

                                <a
                                    class="job-link"
                                    href="job.php?job=<?= urlencode($job['serial_no']) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $job['serial_no']
                                    ) ?>

                                </a>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['purchaserName'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['kva'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['phases'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['hvVoltage'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['lvVoltage'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['vectorGroup'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['quantity'] ?? ''
                                ) ?>
                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $data['jobType'] ?? ''
                                ) ?>
                            </td>
							<td>

    <?php if (!empty($drawingCodes)): ?>

        <a
            href="drawings.php?job=<?= urlencode(
                $job['serial_no']
            ) ?>"
            class="drawing-list"
        >

            <?= htmlspecialchars(
                implode(
                    ' | ',
                    $drawingCodes
                )
            ) ?>

        </a>

    <?php else: ?>

        <span class="muted">
            —
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
