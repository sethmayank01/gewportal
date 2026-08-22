<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Get Drawing ID
|--------------------------------------------------------------------------
*/

$drawingId = (int)($_GET['id'] ?? 0);

if ($drawingId <= 0) {
    die("Invalid drawing.");
}


/*
|--------------------------------------------------------------------------
| Get Drawing
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        job_serial_no,
        section,
        drawing_no,
        title,
        current_revision,
        created_by,
        created_at
    FROM drawings
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $drawingId
]);

$drawing = $stmt->fetch();

if (!$drawing) {
    die("Drawing not found.");
}


/*
|--------------------------------------------------------------------------
| Get Revision History
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        revision,
        file_name,
        file_size,
        uploaded_by,
        uploaded_at,
        change_description
    FROM drawing_revision
    WHERE drawing_id = :drawing_id
    ORDER BY id DESC
");

$stmt->execute([
    'drawing_id' => $drawingId
]);

$revisions = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    <?= htmlspecialchars($drawing['title']) ?>
    - Revision History
</title>

<style>

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f3f5f7;
    color: #222;
}

.header {
    background: #174a7e;
    color: white;
    padding: 15px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.header-title {
    font-size: 20px;
    font-weight: bold;
}

.header-user {
    font-size: 14px;
}

.header-user a {
    color: white;
    text-decoration: none;
    margin-left: 15px;
}

.container {
    max-width: 1200px;
    margin: 30px auto;
    padding: 0 20px;
}

.back {
    display: inline-block;
    margin-bottom: 15px;
    color: #174a7e;
    text-decoration: none;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.drawing-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
}

.drawing-title {
    margin: 0;
    color: #174a7e;
    font-size: 28px;
}

.drawing-info {
    margin-top: 10px;
    color: #666;
    line-height: 1.7;
}

.current {
    display: inline-block;
    background: #e7f1e8;
    color: #28652f;
    padding: 6px 12px;
    border-radius: 15px;
    font-weight: bold;
}

.button {
    display: inline-block;
    padding: 9px 14px;
    background: #174a7e;
    color: white;
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
}

.button:hover {
    background: #123b65;
}

.open-button {
    display: inline-block;
    padding: 6px 10px;
    background: #174a7e;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    font-size: 13px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    background: #eef2f6;
    text-align: left;
    padding: 12px;
    border-bottom: 2px solid #ddd;
}

td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

tr:hover {
    background: #f8fafc;
}

.empty {
    text-align: center;
    padding: 35px;
    color: #777;
}

.current-revision {
    background: #e7f1e8;
    color: #28652f;
    padding: 5px 9px;
    border-radius: 12px;
    font-weight: bold;
    display: inline-block;
}

</style>

</head>


<body>


<div class="header">

    <div class="header-title">
        GEW Transformer Engineering Portal
    </div>

    <div class="header-user">

        User:
        <?= htmlspecialchars(
            $_SESSION['user']['username']
        ) ?>

        |

        <a href="logout.php">
            Logout
        </a>

    </div>

</div>


<div class="container">


<a
    class="back"
    href="drawings.php?job=<?= urlencode($drawing['job_serial_no']) ?>"
>
    ← Back to Drawings
</a>


<!-- Drawing Information -->

<div class="card">

    <div class="drawing-header">

        <div>

            <h1 class="drawing-title">

                <?= htmlspecialchars(
                    $drawing['title']
                ) ?>

            </h1>

            <div class="drawing-info">

                <strong>Job:</strong>
                <?= htmlspecialchars(
                    $drawing['job_serial_no']
                ) ?>

                &nbsp; | &nbsp;

                <strong>Section:</strong>
                <?= htmlspecialchars(
                    $drawing['section']
                ) ?>

                &nbsp; | &nbsp;

                <strong>Drawing No.:</strong>
                <?= htmlspecialchars(
                    $drawing['drawing_no']
                ) ?>

                <br>

                <strong>Created By:</strong>
                <?= htmlspecialchars(
                    $drawing['created_by']
                ) ?>

                &nbsp; | &nbsp;

                <strong>Created On:</strong>
                <?= htmlspecialchars(
                    date(
                        'd-M-Y h:i A',
                        strtotime(
                            $drawing['created_at']
                        )
                    )
                ) ?>

            </div>

        </div>


        <div>

            <?php if (
                !empty($drawing['current_revision'])
            ): ?>

                <span class="current">

                    Current:
                    <?= htmlspecialchars(
                        $drawing['current_revision']
                    ) ?>

                </span>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- Revision History -->

<div class="card">

    <div style="
        display:flex;
        justify-content:space-between;
        align-items:center;
        margin-bottom:20px;
    ">

        <h2 style="margin:0;">
            Revision History
        </h2>

        <a
            class="button"
            href="upload_revision.php?drawing_id=<?= $drawingId ?>"
        >
            + Upload New Revision
        </a>

    </div>


    <?php if (count($revisions) === 0): ?>

        <div class="empty">

            No revisions found.

        </div>

    <?php else: ?>

        <table>

            <thead>

                <tr>

                    <th>Revision</th>
                    <th>File Name</th>
                    <th>Uploaded By</th>
                    <th>Uploaded On</th>
                    <th>Change Description</th>
                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php foreach ($revisions as $revision): ?>

                <tr>

                    <td>

                        <?php if (
                            $revision['revision']
                            === $drawing['current_revision']
                        ): ?>

                            <span class="current-revision">

                                <?= htmlspecialchars(
                                    $revision['revision']
                                ) ?>

                                Current

                            </span>

                        <?php else: ?>

                            <?= htmlspecialchars(
                                $revision['revision']
                            ) ?>

                        <?php endif; ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $revision['file_name']
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $revision['uploaded_by']
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            date(
                                'd-M-Y h:i A',
                                strtotime(
                                    $revision['uploaded_at']
                                )
                            )
                        ) ?>

                    </td>


                    <td>

                        <?= htmlspecialchars(
                            $revision['change_description']
                            ?? ''
                        ) ?>

                    </td>


                    <td>

                        <a
                            class="open-button"
                            href="view_revision.php?id=<?= $revision['id'] ?>"
                            target="_blank"
                        >
                            Open Drawing
                        </a>
						
						<?php
    $currentUserRole =
        $_SESSION['user']['role'] ?? '';
    ?>


    <?php if (
        $currentUserRole === 'admin' &&
        $revision['revision'] !==
        $drawing['current_revision']
    ): ?>

        <form
            method="POST"
            action="delete_revision.php"
            style="
                display:inline;
                margin-left:5px;
            "
            onsubmit="
                return confirm(
                    'Delete revision <?= htmlspecialchars($revision['revision'], ENT_QUOTES) ?>? This will permanently delete this version and its file.'
                );
            "
        >

            <input
                type="hidden"
                name="revision_id"
                value="<?= $revision['id'] ?>"
            >

            <button
                type="submit"
                style="
                    padding:6px 10px;
                    background:#b42318;
                    color:white;
                    border:none;
                    border-radius:4px;
                    cursor:pointer;
                    font-size:13px;
                "
            >
                Delete
            </button>

        </form>

    <?php endif; ?>


                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    <?php endif; ?>

</div>


</div>

</body>

</html>