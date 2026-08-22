<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Drawing storage location
|--------------------------------------------------------------------------
*/

$uploadBasePath = 'C:\\GEW_DesignPortal\\drawings';


/*
|--------------------------------------------------------------------------
| Get Revision ID
|--------------------------------------------------------------------------
*/

$revisionId = (int)($_GET['id'] ?? 0);

if ($revisionId <= 0) {
    http_response_code(400);
    exit('Invalid revision.');
}


/*
|--------------------------------------------------------------------------
| Get Revision
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        dr.id,
        dr.revision,
        dr.file_name,
        dr.file_path,
        dr.file_size,
        d.job_serial_no,
        d.section,
        d.drawing_no,
        d.title
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
| Build Physical File Path
|--------------------------------------------------------------------------
*/

$relativePath = str_replace(
    '/',
    DIRECTORY_SEPARATOR,
    $revision['file_path']
);

$filePath =
    $uploadBasePath
    . DIRECTORY_SEPARATOR
    . $relativePath;


/*
|--------------------------------------------------------------------------
| Check File
|--------------------------------------------------------------------------
*/

if (!file_exists($filePath)) {

    http_response_code(404);

    echo '<h2>File not found on server.</h2>';

    echo '<p>Expected location:</p>';

    echo '<pre>';
    echo htmlspecialchars($filePath);
    echo '</pre>';

    exit;
}


if (!is_file($filePath)) {

    http_response_code(404);

    exit('Invalid file.');

}


/*
|--------------------------------------------------------------------------
| Send PDF to Browser
|--------------------------------------------------------------------------
*/

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mimeType = $finfo->file($filePath);

header('Content-Type: ' . $mimeType);

header(
    'Content-Length: ' . filesize($filePath)
);

header(
    'Content-Disposition: inline; filename="' .
    basename($revision['file_name']) .
    '"'
);

header('X-Content-Type-Options: nosniff');

readfile($filePath);

exit;