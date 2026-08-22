<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Get Revision ID
|--------------------------------------------------------------------------
*/

$revisionId = (int)($_POST['revision_id'] ?? 0);

if ($revisionId <= 0) {
    http_response_code(400);
    exit('Invalid revision.');
}


/*
|--------------------------------------------------------------------------
| Verify User Role From Database
|--------------------------------------------------------------------------
*/

$username = $_SESSION['user']['username'] ?? '';

$stmt = $pdo->prepare("
    SELECT role
    FROM users
    WHERE username = :username
    LIMIT 1
");

$stmt->execute([
    'username' => $username
]);

$userRole = $stmt->fetchColumn();

if ($userRole !== 'admin') {

    http_response_code(403);

    exit(
        'You do not have permission to delete revisions.'
    );

}


/*
|--------------------------------------------------------------------------
| Get Revision + Drawing Information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        dr.id,
        dr.revision,
        dr.file_path,
        d.id AS drawing_id,
        d.job_serial_no,
        d.current_revision
    FROM drawing_revision dr

    INNER JOIN drawings d
        ON d.id = dr.drawing_id

    WHERE dr.id = :id

    LIMIT 1
");

$stmt->execute([
    'id' => $revisionId
]);

$revision = $stmt->fetch();

if (!$revision) {

    http_response_code(404);

    exit('Revision not found.');

}


/*
|--------------------------------------------------------------------------
| Do Not Allow Current Revision To Be Deleted
|--------------------------------------------------------------------------
*/

if (
    $revision['revision'] ===
    $revision['current_revision']
) {

    http_response_code(400);

    exit(
        'The current revision cannot be deleted. '
        . 'Please upload a newer revision first.'
    );

}


/*
|--------------------------------------------------------------------------
| Drawing Storage Location
|--------------------------------------------------------------------------
*/

$uploadBasePath = 'C:\\GEW_DesignPortal\\drawings';


/*
|--------------------------------------------------------------------------
| Build Physical File Path
|--------------------------------------------------------------------------
*/

$filePath = null;

if (!empty($revision['file_path'])) {

    $relativePath = str_replace(
        '/',
        DIRECTORY_SEPARATOR,
        $revision['file_path']
    );

    $filePath =
        $uploadBasePath
        . DIRECTORY_SEPARATOR
        . $relativePath;

}


/*
|--------------------------------------------------------------------------
| Delete Database Record
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    $stmt = $pdo->prepare("
        DELETE FROM drawing_revision
        WHERE id = :id
    ");

    $stmt->execute([
        'id' => $revisionId
    ]);


    if ($stmt->rowCount() !== 1) {

        throw new Exception(
            'Revision could not be deleted.'
        );

    }


    $pdo->commit();


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    exit(
        'Unable to delete revision: '
        . htmlspecialchars($e->getMessage())
    );

}


/*
|--------------------------------------------------------------------------
| Delete Physical File
|--------------------------------------------------------------------------
*/

if (
    $filePath !== null &&
    file_exists($filePath) &&
    is_file($filePath)
) {

    if (!@unlink($filePath)) {

        /*
        |----------------------------------------------------------------------
        | Database record is already deleted.
        | File deletion failure is reported, but we don't restore
        | the database record.
        |----------------------------------------------------------------------
        */

        error_log(
            'Unable to delete drawing revision file: '
            . $filePath
        );

    }

}


/*
|--------------------------------------------------------------------------
| Return To Drawing Revision History
|--------------------------------------------------------------------------
*/

header(
    'Location: drawing.php?id='
    . urlencode($revision['drawing_id'])
);

exit;