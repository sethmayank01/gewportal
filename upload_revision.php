<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$maxFileSize = 25 * 1024 * 1024; // 25 MB

/*
 * Store drawings OUTSIDE the web root.
 *
 * Example:
 * C:\GEW_DesignPortal\drawings\
 */
$uploadBasePath = 'C:\\GEW_DesignPortal\\drawings';


/*
|--------------------------------------------------------------------------
| Get Drawing ID
|--------------------------------------------------------------------------
*/

$drawingId = (int)($_GET['drawing_id'] ?? $_POST['drawing_id'] ?? 0);

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
        current_revision
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
| Generate Next Revision
|--------------------------------------------------------------------------
|
| Rev-00
| Rev-01
| Rev-02
| ...
|
*/

function getNextRevision(PDO $pdo, int $drawingId): string
{
    $stmt = $pdo->prepare("
        SELECT revision
        FROM drawing_revision
        WHERE drawing_id = :drawing_id
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        'drawing_id' => $drawingId
    ]);

    $lastRevision = $stmt->fetchColumn();

    if (!$lastRevision) {
        return 'Rev-00';
    }

    if (preg_match('/^Rev-(\d+)$/i', $lastRevision, $matches)) {

        $number = (int)$matches[1];

        $number++;

        return 'Rev-' . str_pad(
            (string)$number,
            2,
            '0',
            STR_PAD_LEFT
        );
    }

    /*
     * Fallback in case an unexpected revision exists.
     */
    return 'Rev-00';
}


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = '';

$changeDescription = '';


/*
|--------------------------------------------------------------------------
| Handle Upload
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $changeDescription = trim(
        $_POST['change_description'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Drawing File
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_FILES['drawing_file']) ||
        $_FILES['drawing_file']['error'] !== UPLOAD_ERR_OK
    ) {

        $error = 'Please select a drawing file.';

    }


    /*
    |--------------------------------------------------------------------------
    | Process File Validation
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $file = $_FILES['drawing_file'];


        /*
        |--------------------------------------------------------------------------
        | Check File Size
        |--------------------------------------------------------------------------
        */

        if ($file['size'] > $maxFileSize) {

            $error = 'File size cannot exceed 25 MB.';

        }


        /*
        |--------------------------------------------------------------------------
        | Check Extension
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $extension = strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );


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


            if (!in_array(
                $extension,
                $allowedExtensions,
                true
            )) {

                $error =
                    'Unsupported file type. '
                    . 'Allowed: PDF, DOC, DOCX, XLS, XLSX, '
                    . 'JPG, JPEG, PNG and TXT.';

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Verify MIME Type
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mimeType = $finfo->file(
                $file['tmp_name']
            );


            $allowedMimeTypes = [

                'pdf' => [
                    'application/pdf'
                ],

                'doc' => [
                    'application/msword'
                ],

                'docx' => [
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip'
                ],

                'xls' => [
                    'application/vnd.ms-excel'
                ],

                'xlsx' => [
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/zip'
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
                    'text/plain'
                ]

            ];


            if (
                !isset($allowedMimeTypes[$extension]) ||
                !in_array(
                    $mimeType,
                    $allowedMimeTypes[$extension],
                    true
                )
            ) {

                $error =
                    'The selected file does not appear to be a valid '
                    . strtoupper($extension)
                    . ' file.';

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Process Upload
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $pdo->beginTransaction();

        $savedFilePath = null;

        try {

                /*
                |--------------------------------------------------------------------------
                | Lock the drawing
                |--------------------------------------------------------------------------
                |
                | This prevents two users from generating the same
                | revision simultaneously.
                |
                */

                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        job_serial_no,
                        section,
                        drawing_no,
                        title,
                        current_revision
                    FROM drawings
                    WHERE id = :id
                    FOR UPDATE
                ");

                $stmt->execute([
                    'id' => $drawingId
                ]);

                $lockedDrawing = $stmt->fetch();

                if (!$lockedDrawing) {

                    throw new Exception(
                        'Drawing not found.'
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Generate Revision
                |--------------------------------------------------------------------------
                */

                $revision = getNextRevision(
                    $pdo,
                    $drawingId
                );


                /*
                |--------------------------------------------------------------------------
                | Create Directory
                |--------------------------------------------------------------------------
                */

                $jobFolder = preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    $lockedDrawing['job_serial_no']
                );

                $sectionFolder = preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    $lockedDrawing['section']
                );

                $drawingFolder =
                    $uploadBasePath
                    . DIRECTORY_SEPARATOR
                    . $jobFolder
                    . DIRECTORY_SEPARATOR
                    . $sectionFolder;


                if (!is_dir($drawingFolder)) {

                    if (!mkdir(
                        $drawingFolder,
                        0775,
                        true
                    )) {

                        throw new Exception(
                            'Unable to create drawing folder.'
                        );

                    }
                }


                      


                /*
                | Use drawing number + revision as the
                | actual stored filename.
                */

                $safeDrawingNo = preg_replace(
                    '/[^A-Za-z0-9_-]/',
                    '_',
                    $lockedDrawing['drawing_no']
                );

                $extension = strtolower(
    pathinfo(
        $file['name'],
        PATHINFO_EXTENSION
    )
);

$storedFileName =
    $safeDrawingNo
    . '_'
    . $revision
    . '.'
    . $extension;


                $absolutePath =
                    $drawingFolder
                    . DIRECTORY_SEPARATOR
                    . $storedFileName;


                /*
                |--------------------------------------------------------------------------
                | Move Uploaded File
                |--------------------------------------------------------------------------
                */

                if (!move_uploaded_file(
                    $file['tmp_name'],
                    $absolutePath
                )) {

                    throw new Exception(
                        'Unable to save uploaded file.'
                    );

                }

                $savedFilePath = $absolutePath;


                /*
                |--------------------------------------------------------------------------
                | Relative Path
                |--------------------------------------------------------------------------
                |
                | We store this in the database.
                |
                */

                $relativePath =
                    $jobFolder
                    . '/'
                    . $sectionFolder
                    . '/'
                    . $storedFileName;


                /*
                |--------------------------------------------------------------------------
                | Insert Revision
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO drawing_revision
                    (
                        drawing_id,
                        revision,
                        file_name,
                        file_path,
                        file_size,
                        uploaded_by,
                        change_description
                    )
                    VALUES
                    (
                        :drawing_id,
                        :revision,
                        :file_name,
                        :file_path,
                        :file_size,
                        :uploaded_by,
                        :change_description
                    )
                ");

                $stmt->execute([

                    'drawing_id' =>
                        $drawingId,

                    'revision' =>
                        $revision,

                    'file_name' =>
                        $file['name'],

                    'file_path' =>
                        $relativePath,

                    'file_size' =>
                        $file['size'],

                    'uploaded_by' =>
                        $_SESSION['user']['username'],

                    'change_description' =>
                        $changeDescription !== ''
                            ? $changeDescription
                            : null
                ]);


                /*
                |--------------------------------------------------------------------------
                | Update Current Revision
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    UPDATE drawings
                    SET current_revision = :revision
                    WHERE id = :id
                ");

                $stmt->execute([

                    'revision' =>
                        $revision,

                    'id' =>
                        $drawingId

                ]);


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $pdo->commit();


                /*
                |--------------------------------------------------------------------------
                | Return to Drawing
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: drawing.php?id='
                    . $drawingId
                );

                exit;


            } catch (Throwable $e) {

                /*
                |--------------------------------------------------------------------------
                | Rollback Database
                |--------------------------------------------------------------------------
                */

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }


                /*
                |--------------------------------------------------------------------------
                | Delete File If Database Failed
                |--------------------------------------------------------------------------
                */

                if (
                    $savedFilePath !== null &&
                    file_exists($savedFilePath)
                ) {

                    unlink($savedFilePath);

                }


                $error =
                    'Upload failed: '
                    . $e->getMessage();

            }

        }

    }




/*
|--------------------------------------------------------------------------
| Get Next Revision For Display
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
     * Lock only for displaying the next number.
     * We immediately release the lock.
     */

    $stmt = $pdo->prepare("
        SELECT id
        FROM drawings
        WHERE id = :id
        FOR UPDATE
    ");

    $stmt->execute([
        'id' => $drawingId
    ]);

    $nextRevision = getNextRevision(
        $pdo,
        $drawingId
    );

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $nextRevision = 'Rev-00';
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Upload Revision -
    <?= htmlspecialchars($drawing['title']) ?>
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

    max-width: 800px;

    margin: 30px auto;

    padding: 0 20px;

}


.card {

    background: white;

    padding: 25px;

    border-radius: 8px;

    box-shadow: 0 2px 8px rgba(0,0,0,0.08);

}


.back {

    display: inline-block;

    margin-bottom: 15px;

    color: #174a7e;

    text-decoration: none;

}


h1 {

    margin-top: 0;

    color: #174a7e;

}


.info {

    background: #f5f7fa;

    padding: 15px;

    border-radius: 5px;

    margin-bottom: 25px;

    line-height: 1.7;

}


.revision {

    background: #e7f1e8;

    color: #28652f;

    display: inline-block;

    padding: 6px 12px;

    border-radius: 15px;

    font-weight: bold;

}


label {

    display: block;

    font-weight: bold;

    margin-top: 18px;

    margin-bottom: 7px;

}


input[type="file"],
textarea {

    width: 100%;

    box-sizing: border-box;

    padding: 10px;

    border: 1px solid #ccc;

    border-radius: 5px;

    font-family: Arial, sans-serif;

    font-size: 14px;

}


textarea {

    min-height: 100px;

    resize: vertical;

}


button {

    margin-top: 25px;

    padding: 11px 20px;

    background: #174a7e;

    color: white;

    border: none;

    border-radius: 5px;

    cursor: pointer;

    font-size: 14px;

}


button:hover {

    background: #123b65;

}


.cancel {

    display: inline-block;

    margin-left: 10px;

    padding: 10px 18px;

    color: #555;

    text-decoration: none;

}


.error {

    background: #ffe5e5;

    color: #a00000;

    padding: 12px;

    border-radius: 5px;

    margin-bottom: 20px;

}


.note {

    color: #777;

    font-size: 13px;

    margin-top: 6px;

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
    href="drawing.php?id=<?= $drawingId ?>"
>

    ← Back to Drawing

</a>


<div class="card">


<h1>

    Upload New Revision

</h1>


<div class="info">

    <strong>Job:</strong>

    <?= htmlspecialchars(
        $drawing['job_serial_no']
    ) ?>

    <br>


    <strong>Section:</strong>

    <?= htmlspecialchars(
        $drawing['section']
    ) ?>

    <br>


    <strong>Drawing No.:</strong>

    <?= htmlspecialchars(
        $drawing['drawing_no']
    ) ?>

    <br>


    <strong>Title:</strong>

    <?= htmlspecialchars(
        $drawing['title']
    ) ?>

    <br>


    <strong>New Revision:</strong>

    <span class="revision">

        <?= htmlspecialchars(
            $nextRevision
        ) ?>

    </span>

</div>


<?php if ($error !== ''): ?>

    <div class="error">

        <?= htmlspecialchars($error) ?>

    </div>

<?php endif; ?>


<form
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="hidden"
    name="drawing_id"
    value="<?= $drawingId ?>"
>


<label>
    Drawing File
</label>

<input
    type="file"
    name="drawing_file"
    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
    required
>

<div class="note">
    Supported files: PDF, Word, Excel, JPG, PNG and TXT.
    Maximum file size: 25 MB.
</div>


<label>

    Change Description

</label>


<textarea
    name="change_description"
    placeholder="Describe what has changed in this revision..."
><?= htmlspecialchars(
    $changeDescription
) ?></textarea>


<button type="submit">

    Upload Revision

</button>


<a
    class="cancel"
    href="drawing.php?id=<?= $drawingId ?>"
>

    Cancel

</a>


</form>


</div>


</div>


</body>

</html>