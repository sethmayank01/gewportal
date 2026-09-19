<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method.');
}

require 'db.php';

$documentId = (int)($_POST['document_id'] ?? 0);
$submittedToken = (string)($_POST['delete_token'] ?? '');
$sessionToken = (string)($_SESSION['inspection_delete_token'] ?? '');

if ($documentId <= 0) {
    http_response_code(400);
    exit('Invalid inspection document.');
}

if (
    $sessionToken === ''
    || $submittedToken === ''
    || !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit('Invalid or expired delete request.');
}

/*
|--------------------------------------------------------------------------
| Verify The Current Role From The Database
|--------------------------------------------------------------------------
*/

$username = (string)($_SESSION['user']['username'] ?? '');

$roleStmt = $pdo->prepare("
    SELECT role
    FROM users
    WHERE username = :username
    LIMIT 1
");
$roleStmt->execute(['username' => $username]);

if (strtolower((string)$roleStmt->fetchColumn()) !== 'admin') {
    http_response_code(403);
    exit('Only administrators can delete inspection documents.');
}

/*
|--------------------------------------------------------------------------
| Get The Document Before Removing It
|--------------------------------------------------------------------------
*/

$documentStmt = $pdo->prepare("
    SELECT id, job_serial_no, category, file_path
    FROM inspection_documents
    WHERE id = :id
    LIMIT 1
");
$documentStmt->execute(['id' => $documentId]);
$document = $documentStmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Inspection document not found.');
}

$uploadBasePath = 'C:\\GEW_DesignPortal\\inspections';
$filePath = null;
$stagedFilePath = null;

if (!empty($document['file_path'])) {
    $storedPath = (string)$document['file_path'];

    if (
        strpos($storedPath, "\0") !== false
        || preg_match('/(^|[\\\\\/])\.\.([\\\\\/]|$)/', $storedPath)
        || preg_match('/^[A-Za-z]:[\\\\\/]/', $storedPath)
        || str_starts_with($storedPath, '\\\\')
    ) {
        http_response_code(400);
        exit('Invalid inspection document path.');
    }

    $relativePath = str_replace(
        ['/', '\\'],
        DIRECTORY_SEPARATOR,
        $storedPath
    );

    $filePath =
        $uploadBasePath
        . DIRECTORY_SEPARATOR
        . ltrim($relativePath, DIRECTORY_SEPARATOR);
}

/*
|--------------------------------------------------------------------------
| Stage File, Delete Record, Then Remove File
|--------------------------------------------------------------------------
|
| Renaming first keeps the current file recoverable if the database delete
| fails. A missing physical file does not prevent removal of a stale record.
|
*/

try {
    if ($filePath !== null && is_file($filePath)) {
        $baseRealPath = realpath($uploadBasePath);
        $fileRealPath = realpath($filePath);

        if (
            $baseRealPath === false
            || $fileRealPath === false
            || stripos(
                $fileRealPath,
                rtrim($baseRealPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            ) !== 0
        ) {
            throw new RuntimeException('Invalid inspection document location.');
        }

        $stagedFilePath =
            $filePath
            . '.deleting-'
            . bin2hex(random_bytes(8));

        if (!rename($filePath, $stagedFilePath)) {
            throw new RuntimeException(
                'Could not prepare the inspection document for deletion.'
            );
        }
    }

    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare("
        DELETE FROM inspection_documents
        WHERE id = :id
    ");
    $deleteStmt->execute(['id' => $documentId]);

    if ($deleteStmt->rowCount() !== 1) {
        throw new RuntimeException('Inspection document could not be deleted.');
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (
        $stagedFilePath !== null
        && is_file($stagedFilePath)
        && $filePath !== null
        && !file_exists($filePath)
    ) {
        @rename($stagedFilePath, $filePath);
    }

    http_response_code(500);
    exit(
        'Unable to delete inspection document: '
        . htmlspecialchars($exception->getMessage())
    );
}

if ($stagedFilePath !== null && is_file($stagedFilePath)) {
    if (!@unlink($stagedFilePath)) {
        error_log(
            'Unable to remove deleted inspection document file: '
            . $stagedFilePath
        );
    }
}

unset($_SESSION['inspection_delete_token']);

header(
    'Location: inspection.php?job='
    . urlencode($document['job_serial_no'])
    . '&deleted=1'
);
exit;
