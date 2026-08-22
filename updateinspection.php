<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$uploadBasePath = 'C:\\GEW_DesignPortal\\inspections';


/*
|--------------------------------------------------------------------------
| Inspection Categories
|--------------------------------------------------------------------------
|
| Keep this list synchronized with inspection.php.
| It controls which categories can be uploaded.
|
*/

$inspectionCategories = [

    'Incoming Inspection' => [
        'Core',
        'Copper',
        'Tank',
        'Radiator'
    ],

    'In-Process Inspection' => [
        'Winding',
        'Core Assembly',
        'Core Coil Assembly',
        'Tanking'
    ],

    'Final Testing' => [
        'Final Testing'
    ]

];


/*
|--------------------------------------------------------------------------
| Helper - Get Current User
|--------------------------------------------------------------------------
*/

function currentUserName(): string
{
    $user = $_SESSION['user'] ?? '';

    if (is_array($user)) {

        return (string)(
            $user['username']
            ?? $user['name']
            ?? $user['user_name']
            ?? $user['email']
            ?? 'Unknown'
        );

    }

    return (string)$user;
}


/*
|--------------------------------------------------------------------------
| Helper - Current Role
|--------------------------------------------------------------------------
*/

function currentUserRole(): string
{
    $user = $_SESSION['user'] ?? '';

    if (is_array($user)) {

        return strtolower(
            (string)(
                $user['role']
                ?? ''
            )
        );

    }

    return '';
}


/*
|--------------------------------------------------------------------------
| Helper - Is Valid Category
|--------------------------------------------------------------------------
*/

function categoryExists(
    string $category,
    array $inspectionCategories
): bool {

    foreach (
        $inspectionCategories
        as $categories
    ) {

        if (
            in_array(
                $category,
                $categories,
                true
            )
        ) {

            return true;

        }

    }

    return false;
}


/*
|--------------------------------------------------------------------------
| Helper - Safe File Name
|--------------------------------------------------------------------------
*/

function safeFileName(string $fileName): string
{
    $fileName =
        basename($fileName);

    $fileName =
        preg_replace(
            '/[^A-Za-z0-9._-]/',
            '_',
            $fileName
        );

    return trim(
        $fileName,
        '._'
    );
}


/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    exit('Invalid request method.');

}


/*
|--------------------------------------------------------------------------
| Read Request
|--------------------------------------------------------------------------
*/

$jobNo =
    trim(
        $_POST['job'] ?? ''
    );

$category =
    trim(
        $_POST['category'] ?? ''
    );

$mode =
    strtolower(
        trim(
            $_POST['mode'] ?? 'upload'
        )
    );


/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/

if ($jobNo === '') {

    http_response_code(400);

    exit('Invalid job number.');

}


if ($category === '') {

    http_response_code(400);

    exit('Inspection category is required.');

}


if (
    !categoryExists(
        $category,
        $inspectionCategories
    )
) {

    http_response_code(400);

    exit('Invalid inspection category.');

}


if (
    $mode !== 'upload'
    &&
    $mode !== 'replace'
) {

    http_response_code(400);

    exit('Invalid upload mode.');

}


/*
|--------------------------------------------------------------------------
| Replace Requires Admin
|--------------------------------------------------------------------------
*/

$isAdmin =
    currentUserRole() === 'admin';


/*
|--------------------------------------------------------------------------
| Get Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        serial_no
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
| Check Uploaded File
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_FILES['inspection_file']
    )
) {

    http_response_code(400);

    exit('No file was selected.');

}


$file =
    $_FILES['inspection_file'];


if (
    $file['error']
    !== UPLOAD_ERR_OK
) {

    $uploadErrors = [

        UPLOAD_ERR_INI_SIZE =>
            'The uploaded file exceeds the server upload limit.',

        UPLOAD_ERR_FORM_SIZE =>
            'The uploaded file exceeds the allowed form size.',

        UPLOAD_ERR_PARTIAL =>
            'The file was only partially uploaded.',

        UPLOAD_ERR_NO_FILE =>
            'No file was selected.',

        UPLOAD_ERR_NO_TMP_DIR =>
            'Temporary upload directory is missing.',

        UPLOAD_ERR_CANT_WRITE =>
            'The server could not write the uploaded file.',

        UPLOAD_ERR_EXTENSION =>
            'A server extension stopped the upload.'

    ];


    $message =
        $uploadErrors[
            $file['error']
        ]
        ??
        'File upload failed.';


    http_response_code(400);

    exit($message);

}


/*
|--------------------------------------------------------------------------
| File Size
|--------------------------------------------------------------------------
*/

$fileSize =
    (int)$file['size'];


/*
|--------------------------------------------------------------------------
| Maximum File Size
|--------------------------------------------------------------------------
|
| 25 MB per inspection document.
|
*/

$maxFileSize =
    25 * 1024 * 1024;


if (
    $fileSize <= 0
) {

    http_response_code(400);

    exit('Invalid empty file.');

}


if (
    $fileSize > $maxFileSize
) {

    http_response_code(400);

    exit('File is larger than the allowed 25 MB limit.');

}


/*
|--------------------------------------------------------------------------
| Original File Name
|--------------------------------------------------------------------------
*/

$originalFileName =
    safeFileName(
        $file['name']
    );


if (
    $originalFileName === ''
) {

    http_response_code(400);

    exit('Invalid file name.');

}


/*
|--------------------------------------------------------------------------
| Extension
|--------------------------------------------------------------------------
*/

$extension =
    strtolower(
        pathinfo(
            $originalFileName,
            PATHINFO_EXTENSION
        )
    );


/*
|--------------------------------------------------------------------------
| Allowed File Types
|--------------------------------------------------------------------------
*/

$allowedExtensions = [

    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'jpg',
    'jpeg',
    'png',
    'txt'

];


if (
    !in_array(
        $extension,
        $allowedExtensions,
        true
    )
) {

    http_response_code(400);

    exit(
        'Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG and TXT.'
    );

}


/*
|--------------------------------------------------------------------------
| MIME Type
|--------------------------------------------------------------------------
*/

$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );

$mimeType =
    $finfo->file(
        $file['tmp_name']
    );


$allowedMimeTypes = [

    'pdf' => [
        'application/pdf'
    ],

    'doc' => [
        'application/msword',
        'application/octet-stream'
    ],

    'docx' => [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/octet-stream'
    ],

    'xls' => [
        'application/vnd.ms-excel',
        'application/octet-stream'
    ],

    'xlsx' => [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream'
    ],

    'jpg' => [
        'image/jpeg'
    ],

    'jpeg' => [
        'image/jpeg'
    ],

    'png' => [
        'image/png'
    ],

    'txt' => [
        'text/plain',
        'application/octet-stream'
    ]

];


if (
    isset(
        $allowedMimeTypes[$extension]
    )
    &&
    !in_array(
        $mimeType,
        $allowedMimeTypes[$extension],
        true
    )
) {

    http_response_code(400);

    exit(
        'The uploaded file type does not match its file extension.'
    );

}


/*
|--------------------------------------------------------------------------
| Check Existing Document
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        file_path,
        file_name
    FROM inspection_documents
    WHERE job_serial_no = :job_serial_no
      AND category = :category
    LIMIT 1
");

$stmt->execute([

    'job_serial_no' => $jobNo,

    'category' => $category

]);

$existing =
    $stmt->fetch();


/*
|--------------------------------------------------------------------------
| Existing Document Rules
|--------------------------------------------------------------------------
*/

if ($existing) {

    /*
    | Existing document = replacement.
    | Only admin is allowed.
    */

    if (!$isAdmin) {

        http_response_code(403);

        exit(
            'Only administrators can replace an existing inspection document.'
        );

    }

    $mode = 'replace';

} else {

    /*
    | No existing document = first upload.
    */

    $mode = 'upload';

}


/*
|--------------------------------------------------------------------------
| Create Job Directory
|--------------------------------------------------------------------------
*/

$jobDirectory =
    $uploadBasePath
    . DIRECTORY_SEPARATOR
    . safeFileName(
        $jobNo
    );


if (
    !is_dir(
        $jobDirectory
    )
) {

    if (
        !mkdir(
            $jobDirectory,
            0775,
            true
        )
    ) {

        http_response_code(500);

        exit(
            'Could not create the inspection document folder.'
        );

    }

}


/*
|--------------------------------------------------------------------------
| Generate Stored File Name
|--------------------------------------------------------------------------
|
| Keep the original file name visible to users,
| but use a unique server-side name to prevent
| accidental overwriting.
|
*/

/*
|--------------------------------------------------------------------------
| Generate Meaningful Stored File Name
|--------------------------------------------------------------------------
|
| Format:
| JOBNO_CATEGORY_Inspection.EXT
|
| Example:
| TRFD0731_Core_Inspection.pdf
|
*/

$safeJobNo =
    safeFileName($jobNo);

$safeCategory =
    safeFileName($category);

$storedFileName =
    $safeJobNo
    . '_'
    . $safeCategory
    . '_Inspection.'
    . $extension;


$storedFileName =
    safeFileName(
        $storedFileName
    );


$physicalFilePath =
    $jobDirectory
    . DIRECTORY_SEPARATOR
    . $storedFileName;


/*
|--------------------------------------------------------------------------
| Relative Path Stored In Database
|--------------------------------------------------------------------------
*/

$relativeFilePath =
    $jobNo
    . '/'
    . $storedFileName;


/*
|--------------------------------------------------------------------------
| Upload To A Temporary File First
|--------------------------------------------------------------------------
|
| Never delete or overwrite the current document before the new upload has
| been safely written. The temporary file is created in the same directory
| so it can be renamed atomically when the replacement is activated.
|
*/

$temporaryFilePath =
    $physicalFilePath
    . '.upload-'
    . bin2hex(random_bytes(8));

$backupFilePath = null;
$oldPhysicalPath = null;
$newFileActivated = false;

if (
    !move_uploaded_file(
        $file['tmp_name'],
        $temporaryFilePath
    )
) {

    http_response_code(500);

    exit(
        'Could not save the uploaded file on the server.'
    );

}


if (
    $existing
    &&
    !empty($existing['file_path'])
) {

    $oldRelativePath =
        str_replace(
            ['/', '\\'],
            DIRECTORY_SEPARATOR,
            $existing['file_path']
        );

    $oldPhysicalPath =
        $uploadBasePath
        . DIRECTORY_SEPARATOR
        . $oldRelativePath;

}


/*
|--------------------------------------------------------------------------
| Current User
|--------------------------------------------------------------------------
*/

$userName =
    currentUserName();


/*
|--------------------------------------------------------------------------
| Database Update
|--------------------------------------------------------------------------
*/

try {

    /*
    |----------------------------------------------------------------------
    | Activate The New File
    |----------------------------------------------------------------------
    |
    | When the old and new paths are identical, move the old file aside
    | first. If anything after this point fails, the catch block restores it.
    |
    */

    if (
        $oldPhysicalPath !== null
        &&
        is_file($oldPhysicalPath)
        &&
        strcasecmp($oldPhysicalPath, $physicalFilePath) === 0
    ) {

        $backupFilePath =
            $oldPhysicalPath
            . '.previous-'
            . bin2hex(random_bytes(8));

        if (!rename($oldPhysicalPath, $backupFilePath)) {
            throw new RuntimeException(
                'Could not prepare the existing inspection document for replacement.'
            );
        }

    } elseif (is_file($physicalFilePath)) {

        throw new RuntimeException(
            'A conflicting inspection document already exists on the server.'
        );

    }

    if (!rename($temporaryFilePath, $physicalFilePath)) {
        throw new RuntimeException(
            'Could not activate the new inspection document.'
        );
    }

    $newFileActivated = true;

    $pdo->beginTransaction();


    if ($existing) {

        /*
        |--------------------------------------------------------------------------
        | Replacement
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE inspection_documents
            SET
                file_name = :file_name,
                file_path = :file_path,
                file_size = :file_size,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");


        $stmt->execute([

            'file_name' =>
                $originalFileName,

            'file_path' =>
                $relativeFilePath,

            'file_size' =>
                $fileSize,

            'updated_by' =>
                $userName,

            'id' =>
                $existing['id']

        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | First Upload
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO inspection_documents
            (
                job_serial_no,
                category,
                file_name,
                file_path,
                file_size,
                uploaded_by,
                created_at,
                updated_by,
                updated_at
            )
            VALUES
            (
                :job_serial_no,
                :category,
                :file_name,
                :file_path,
                :file_size,
                :uploaded_by,
                CURRENT_TIMESTAMP,
                :updated_by,
                CURRENT_TIMESTAMP
            )
        ");


        $stmt->execute([

            'job_serial_no' =>
                $jobNo,

            'category' =>
                $category,

            'file_name' =>
                $originalFileName,

            'file_path' =>
                $relativeFilePath,

            'file_size' =>
                $fileSize,

            'uploaded_by' =>
                $userName,

            'updated_by' =>
                $userName

        ]);

    }


    $pdo->commit();


} catch (
    Throwable $e
) {

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    if ($newFileActivated && is_file($physicalFilePath)) {
        @unlink($physicalFilePath);
    }

    if ($backupFilePath !== null && is_file($backupFilePath)) {
        @rename($backupFilePath, $oldPhysicalPath);
    }

    if (is_file($temporaryFilePath)) {
        @unlink($temporaryFilePath);
    }


    http_response_code(500);

    exit(
        'Database update failed: '
        . htmlspecialchars(
            $e->getMessage()
        )
    );

}


/*
|--------------------------------------------------------------------------
| Remove The Previous File Only After A Successful Database Commit
|--------------------------------------------------------------------------
*/

if ($backupFilePath !== null && is_file($backupFilePath)) {
    @unlink($backupFilePath);
} elseif (
    $oldPhysicalPath !== null
    &&
    is_file($oldPhysicalPath)
    &&
    strcasecmp($oldPhysicalPath, $physicalFilePath) !== 0
) {
    @unlink($oldPhysicalPath);
}


/*
|--------------------------------------------------------------------------
| Return To Inspection Page
|--------------------------------------------------------------------------
*/

header(
    'Location: inspection.php?job='
    . urlencode($jobNo)
);

exit;
