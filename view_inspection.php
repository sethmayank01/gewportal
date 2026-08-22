<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Inspection document storage location
|--------------------------------------------------------------------------
*/

$uploadBasePath = 'C:\\GEW_DesignPortal\\inspections';


/*
|--------------------------------------------------------------------------
| Get Document ID
|--------------------------------------------------------------------------
*/

$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid inspection document.');
}


/*
|--------------------------------------------------------------------------
| Get Document
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        job_serial_no,
        category,
        file_name,
        file_path,
        file_size
    FROM inspection_documents
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    'id' => $documentId
]);

$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Inspection document not found.');
}


/*
|--------------------------------------------------------------------------
| Build Physical File Path
|--------------------------------------------------------------------------
*/

$relativePath = str_replace(
    '/',
    DIRECTORY_SEPARATOR,
    $document['file_path']
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

    echo '<h2>Inspection document not found on server.</h2>';

    echo '<p>Expected location:</p>';

    echo '<pre>';
    echo htmlspecialchars($filePath);
    echo '</pre>';

    exit;
}


if (!is_file($filePath)) {
    http_response_code(404);
    exit('Invalid inspection document.');
}


/*
|--------------------------------------------------------------------------
| Determine MIME Type
|--------------------------------------------------------------------------
*/

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mimeType = $finfo->file($filePath);

if (!$mimeType) {
    $mimeType = 'application/octet-stream';
}


/*
|--------------------------------------------------------------------------
| Send File To Browser
|--------------------------------------------------------------------------
*/

header(
    'Content-Type: ' . $mimeType
);

header(
    'Content-Length: ' . filesize($filePath)
);

header(
    'Content-Disposition: inline; filename="' .
    basename($document['file_name']) .
    '"'
);

header(
    'X-Content-Type-Options: nosniff'
);


/*
|--------------------------------------------------------------------------
| Output File
|--------------------------------------------------------------------------
*/

readfile($filePath);

exit;