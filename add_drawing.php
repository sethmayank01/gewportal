<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';
require_once 'includes/mailer.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$maxFileSize = 25 * 1024 * 1024; // 25 MB

$uploadBasePath = 'C:\\GEW_DesignPortal\\drawings';


/*
|--------------------------------------------------------------------------
| Get Job Number
|--------------------------------------------------------------------------
*/

$jobNo = trim($_GET['job'] ?? $_POST['job'] ?? '');

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
    SELECT serial_no, data
    FROM jobs
    WHERE serial_no = :serial_no
    LIMIT 1
");

$stmt->execute([
    'serial_no' => $jobNo
]);

$job = $stmt->fetch();

if (!$job) {
    die("Job not found.");
}


require 'includes/general_config.php';

$error = '';

$section = '';
$drawingNo = '';	
$title = '';
$changeDescription = 'Initial revision';


/*
|--------------------------------------------------------------------------
| Create Drawing + Rev-00
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $section = trim($_POST['section'] ?? '');
    $drawingNo = trim($_POST['drawing_no'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $changeDescription = trim(
        $_POST['change_description'] ?? ''
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Drawing Details
    |--------------------------------------------------------------------------
    */

    if (!in_array($section, $sections, true)) {

        $error = 'Please select a valid drawing section.';

    } elseif ($drawingNo === '') {

        $error = 'Drawing number is required.';

    } elseif ($title === '') {

        $error = 'Drawing title is required.';

    }

/*
|--------------------------------------------------------------------------
| Validate Drawing File
|--------------------------------------------------------------------------
*/

if (
    $error === '' &&
    (
        !isset($_FILES['drawing_file']) ||
        $_FILES['drawing_file']['error'] !== UPLOAD_ERR_OK
    )
) {

    $error = 'Please select a drawing file.';

}


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
                . 'Allowed: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG and TXT.';

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Verify File MIME Type
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
    | Create Drawing
    |--------------------------------------------------------------------------
    */

    if ($error === '') {

        $pdo->beginTransaction();

        $savedFilePath = null;

        try {

            /*
            |--------------------------------------------------------------------------
            | Check Duplicate Drawing
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM drawings
                WHERE job_serial_no = :job
                  AND section = :section
                  AND drawing_no = :drawing_no
                LIMIT 1
            ");

            $stmt->execute([
                'job' => $jobNo,
                'section' => $section,
                'drawing_no' => $drawingNo
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'A drawing with this Drawing Number already exists '
                    . 'for this job and section.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Insert Drawing Master
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO drawings
                (
                    job_serial_no,
                    section,
                    drawing_no,
                    title,
                    current_revision,
                    created_by
                )
                VALUES
                (
                    :job,
                    :section,
                    :drawing_no,
                    :title,
                    :revision,
                    :created_by
                )
                RETURNING id
            ");

            $stmt->execute([
                'job' =>
                    $jobNo,

                'section' =>
                    $section,

                'drawing_no' =>
                    $drawingNo,

                'title' =>
                    $title,

                'revision' =>
                    'Rev-00',

                'created_by' =>
                    $_SESSION['user']['username']
            ]);

            $drawingId = $stmt->fetchColumn();


            /*
            |--------------------------------------------------------------------------
            | Create Folder
            |--------------------------------------------------------------------------
            */

            $jobFolder = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $jobNo
            );

            $sectionFolder = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $section
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
            |--------------------------------------------------------------------------
            | Generate Stored File Name
            |--------------------------------------------------------------------------
            */

            $safeDrawingNo = preg_replace(
                '/[^A-Za-z0-9_-]/',
                '_',
                $drawingNo
            );

           $extension = strtolower(
    pathinfo(
        $file['name'],
        PATHINFO_EXTENSION
    )
);

$storedFileName =
    $safeDrawingNo
    . '_Rev-00.'
    . $extension;


            $absolutePath =
                $drawingFolder
                . DIRECTORY_SEPARATOR
                . $storedFileName;


            /*
            |--------------------------------------------------------------------------
            | Save Drawing File
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

			if (!file_exists($absolutePath)) {

				throw new Exception(
					'File upload reported success, but the file could not be found.'
				);

}

            $savedFilePath = $absolutePath;


            /*
            |--------------------------------------------------------------------------
            | Relative File Path
            |--------------------------------------------------------------------------
            */

            $relativePath =
                $jobFolder
                . '/'
                . $sectionFolder
                . '/'
                . $storedFileName;


            /*
            |--------------------------------------------------------------------------
            | Insert Rev-00
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
                    'Rev-00',

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
                        : 'Initial drawing'
            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Send New Drawing Notification
            |--------------------------------------------------------------------------
            */

            try {

                sendDrawingRevisionNotification(
                    $pdo,
                    $drawingId,
                    'Rev-00'
                );

            } catch (Throwable $mailError) {

                /*
                 * Drawing upload must remain successful
                 * even if email sending fails.
                 */

                error_log(
                    'New drawing notification email failed: '
                    . $mailError->getMessage()
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Return to Drawing List
            |--------------------------------------------------------------------------
            */

            header(
                'Location: drawings.php?job='
                . urlencode($jobNo)
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            /*
            |--------------------------------------------------------------------------
            | Remove PDF if Database Operation Failed
            |--------------------------------------------------------------------------
            */

            if (
                $savedFilePath !== null &&
                file_exists($savedFilePath)
            ) {

                unlink($savedFilePath);

            }


            $error =
                $e->getMessage();

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Add Drawing - <?= htmlspecialchars($jobNo) ?>
</title>
<link
    href="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/css/tom-select.css"
    rel="stylesheet"
>

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


.job-info {

    background: #f5f7fa;

    padding: 12px;

    border-radius: 5px;

    margin-bottom: 25px;

    line-height: 1.7;

}


label {

    display: block;

    font-weight: bold;

    margin-top: 18px;

    margin-bottom: 6px;

}


input,
select,
textarea {

    width: 100%;

    padding: 10px;

    box-sizing: border-box;

    border: 1px solid #ccc;

    border-radius: 5px;

    font-size: 14px;

}


textarea {

    min-height: 90px;

    resize: vertical;

}


input[type="file"] {

    padding: 8px;

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
    href="drawings.php?job=<?= urlencode($jobNo) ?>"
>

    ← Back to Drawings

</a>


<div class="card">


<h1>

    Add Drawing

</h1>


<div class="job-info">

    <strong>Job:</strong>

    <?= htmlspecialchars($jobNo) ?>

    <br>

    <strong>Purchaser:</strong>

    <?= htmlspecialchars(
        $data['purchaserName'] ?? ''
    ) ?>

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
    name="job"
    value="<?= htmlspecialchars($jobNo) ?>"
>


<label>
    Section
</label>

<select
    name="section"
    id="section"
    required
>
    <option value="">
        Select Section
    </option>

    <?php foreach ($sections as $item): ?>

        <option
            value="<?= htmlspecialchars($item) ?>"
            <?= $section === $item ? 'selected' : '' ?>
        >
            <?= htmlspecialchars($item) ?>
        </option>

    <?php endforeach; ?>

</select>

<label>

    Drawing Number

</label>


<input
    type="text"
    id="drawing_no"
    name="drawing_no"
    value="<?= htmlspecialchars($drawingNo) ?>"
    placeholder="e.g. GEW-T-0730"
    required
>


<label>

    Drawing Title

</label>


<input
    type="text"
    id="title"
    name="title"
    value="<?= htmlspecialchars($title) ?>"
    placeholder="e.g. Tank Assembly"
    required
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
    placeholder="e.g. Initial drawing"
><?= htmlspecialchars(
    $changeDescription
) ?></textarea>


<button type="submit">

    Create Drawing

</button>


<a
    class="cancel"
    href="drawings.php?job=<?= urlencode($jobNo) ?>"
>

    Cancel

</a>


</form>


</div>


</div>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.4.3/dist/js/tom-select.complete.min.js"></script>
<script>

const sectionTomSelect = new TomSelect('#section', {
    create: false,
    allowEmptyOption: true,
    maxOptions: null,
    placeholder: 'Select or search section...'
});

</script>
<script>

const sectionDefaults = <?= json_encode($sectionDefaults) ?>;

const jobNo = <?= json_encode($jobNo) ?>;

const sectionSelect =
    document.getElementById('section');

const drawingNoInput =
    document.getElementById('drawing_no');

const titleInput =
    document.getElementById('title');


sectionSelect.addEventListener('change', function () {

    const section = this.value;

    if (!sectionDefaults[section]) {
        return;
    }

    const defaults = sectionDefaults[section];

    drawingNoInput.value =
        'GEW-' + defaults.code + '-' + jobNo;

    titleInput.value =
        defaults.title;

});

</script>

</body>

</html>
