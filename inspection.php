<?php

session_start();

if (!isset($_SESSION['user'])) {
    http_response_code(403);
    exit('Access denied.');
}

require 'db.php';

$jobNo = trim($_GET['job'] ?? '');

if ($jobNo === '') {
    http_response_code(400);
    exit('Invalid job number.');
}


/*
|--------------------------------------------------------------------------
| Get Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        serial_no,
        data
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
| Decode Job Data
|--------------------------------------------------------------------------
*/

$jobData = [];

if (!empty($job['data'])) {

    $decoded = json_decode(
        $job['data'],
        true
    );

    if (is_array($decoded)) {
        $jobData = $decoded;
    }

}

/*
|--------------------------------------------------------------------------
| Common Job Header expects $data
|--------------------------------------------------------------------------
*/

$data = $jobData;


/*
|--------------------------------------------------------------------------
| Inspection Categories
|--------------------------------------------------------------------------
|
| Add/remove categories here without changing the database.
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
| Existing Documents
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        category,
        file_name,
        file_path,
        file_size,
        uploaded_by,
        created_at,
        updated_by,
        updated_at
    FROM inspection_documents
    WHERE job_serial_no = :job_serial_no
    ORDER BY id
");

$stmt->execute([
    'job_serial_no' => $jobNo
]);

$documents = $stmt->fetchAll();


$documentByCategory = [];

foreach ($documents as $document) {

    $documentByCategory[
        $document['category']
    ] = $document;

}


/*
|--------------------------------------------------------------------------
| Format Date/Time
|--------------------------------------------------------------------------
*/

function formatDateTime($value): string
{
    if (empty($value)) {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date(
        'd-M-Y h:i A',
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| Summary
|--------------------------------------------------------------------------
*/

$totalCategories = 0;
$totalUploaded = 0;

foreach (
    $inspectionCategories
    as $categories
) {

    foreach (
        $categories
        as $category
    ) {

        $totalCategories++;

        if (
            isset(
                $documentByCategory[$category]
            )
        ) {

            $totalUploaded++;

        }

    }

}


/*
|--------------------------------------------------------------------------
| User Role
|--------------------------------------------------------------------------
*/

$currentUserRole =
    strtolower(
        $_SESSION['user']['role'] ?? ''
    );

$isAdmin =
    ($currentUserRole === 'admin');


/*
|--------------------------------------------------------------------------
| Common Header
|--------------------------------------------------------------------------
*/

$pageTitle =
    'Inspection Documents - ' . $jobNo;

require 'includes/header.php';

?>


<div class="container">


    <!-- =====================================================
         BACK TO JOB
         ===================================================== -->

    <a
        class="back"
        href="job.php?job=<?= urlencode($jobNo) ?>"
    >

        ← Back to Job

    </a>


    <!-- =====================================================
         COMMON JOB HEADER
         ===================================================== -->

    <?php require 'includes/job_header.php'; ?>


    <!-- =====================================================
         SUMMARY
         ===================================================== -->

    <div class="summary">

        Inspection Documents:

        <strong>
            <?= $totalUploaded ?>
        </strong>

        of

        <strong>
            <?= $totalCategories ?>
        </strong>

        uploaded

    </div>


    <!-- =====================================================
         INSPECTION TABLE
         ===================================================== -->

    <div class="inspection-card">

        <table class="inspection-table">

            <thead>

                <tr>

                    <th>
                        Stage
                    </th>

                    <th>
                        Inspection Document
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Uploaded By
                    </th>

                    <th>
                        Date &amp; Time
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php foreach (
                $inspectionCategories
                as $groupName =>
                $categories
            ): ?>


                <?php foreach (
                    $categories
                    as $category
                ): ?>


                    <?php

                    $document =
                        $documentByCategory[
                            $category
                        ] ?? null;

                    ?>


                    <tr>


                        <td>

                            <span class="stage">

                                <?= htmlspecialchars(
                                    $groupName
                                ) ?>

                            </span>

                        </td>


                        <td>

                            <?= htmlspecialchars(
                                $category
                            ) ?>

                        </td>


                        <td>


                            <?php if ($document): ?>

                                <span
                                    class="
                                        status
                                        status-uploaded
                                    "
                                >

                                    ✓ Uploaded

                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        status
                                        status-missing
                                    "
                                >

                                    Not Uploaded

                                </span>

                            <?php endif; ?>


                        </td>


                        <td>


                            <?php if ($document): ?>


                                <div class="user-name">

                                    <?= htmlspecialchars(
                                        $document['uploaded_by']
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $document['updated_by']
                                    )
                                    &&
                                    $document['updated_by']
                                    !==
                                    $document['uploaded_by']
                                ): ?>


                                    <div
                                        class="updated-info"
                                    >

                                        Replaced by:

                                        <?= htmlspecialchars(
                                            $document['updated_by']
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                            <?php else: ?>

                                —

                            <?php endif; ?>


                        </td>


                        <td>


                            <?php if ($document): ?>


                                <div class="date-time">

                                    <?= formatDateTime(
                                        $document['created_at']
                                    ) ?>

                                </div>


                                <?php if (
                                    !empty(
                                        $document['updated_by']
                                    )
                                    &&
                                    $document['updated_by']
                                    !==
                                    $document['uploaded_by']
                                    &&
                                    !empty(
                                        $document['updated_at']
                                    )
                                ): ?>


                                    <div
                                        class="updated-info"
                                    >

                                        Replaced:

                                        <?= formatDateTime(
                                            $document['updated_at']
                                        ) ?>

                                    </div>


                                <?php endif; ?>


                            <?php else: ?>

                                —

                            <?php endif; ?>


                        </td>


                        <td>


                            <?php if ($document): ?>


                                <div class="actions">


                                    <a
                                        href="view_inspection.php?id=<?= (int)$document['id'] ?>"
                                        target="_blank"
                                        class="
                                            button
                                            button-primary
                                        "
                                    >

                                        View

                                    </a>


                                    <?php if ($isAdmin): ?>


                                        <button
                                            type="button"
                                            class="
                                                button
                                                button-secondary
                                            "
                                            onclick="openUploadModal(
                                                '<?= htmlspecialchars(
                                                    $category,
                                                    ENT_QUOTES
                                                ) ?>',
                                                true
                                            )"
                                        >

                                            Replace

                                        </button>


                                    <?php endif; ?>


                                </div>


                            <?php else: ?>


                                <button
                                    type="button"
                                    class="
                                        button
                                        button-primary
                                    "
                                    onclick="openUploadModal(
                                        '<?= htmlspecialchars(
                                            $category,
                                            ENT_QUOTES
                                        ) ?>',
                                        false
                                    )"
                                >

                                    Upload

                                </button>


                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


            <?php endforeach; ?>


            </tbody>

        </table>

    </div>

</div>


<!-- =========================================================
     UPLOAD / REPLACE INSPECTION DOCUMENT MODAL
     ========================================================= -->

<div
    id="uploadModal"
    class="modal-overlay"
    onclick="closeUploadModalOnBackdrop(event)"
>

    <div
        class="upload-modal"
        onclick="event.stopPropagation()"
    >


        <div class="modal-header">

            <h2 id="uploadModalTitle">

                Upload Inspection Document

            </h2>


            <button
                type="button"
                class="modal-close"
                onclick="closeUploadModal()"
                aria-label="Close"
            >

                &times;

            </button>

        </div>


        <form
            method="POST"
            action="updateinspection.php"
            enctype="multipart/form-data"
        >


            <input
                type="hidden"
                name="job"
                value="<?= htmlspecialchars($jobNo) ?>"
            >


            <input
                type="hidden"
                name="category"
                id="uploadCategory"
                value=""
            >


            <input
                type="hidden"
                name="mode"
                id="uploadMode"
                value="upload"
            >


            <div class="modal-body">


                <div class="upload-category">

                    Inspection Document:

                    <strong
                        id="uploadCategoryLabel"
                    ></strong>

                </div>


                <input
                    type="file"
                    name="inspection_file"
                    id="inspectionFile"
                    class="file-input"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.txt"
                    required
                >


                <div class="file-help">

                    Allowed: PDF, Word, Excel, JPG,
                    PNG and TXT.
                    Maximum size: 25 MB.

                </div>


                <div class="modal-actions">


                    <button
                        type="button"
                        class="
                            button
                            button-cancel
                        "
                        onclick="closeUploadModal()"
                    >

                        Cancel

                    </button>


                    <button
                        type="submit"
                        class="
                            button
                            button-upload
                        "
                    >

                        Upload

                    </button>


                </div>


            </div>


        </form>

    </div>

</div>


<script>

function openUploadModal(
    category,
    isReplace
)
{

    const modal =
        document.getElementById(
            'uploadModal'
        );

    const categoryInput =
        document.getElementById(
            'uploadCategory'
        );

    const categoryLabel =
        document.getElementById(
            'uploadCategoryLabel'
        );

    const modeInput =
        document.getElementById(
            'uploadMode'
        );

    const title =
        document.getElementById(
            'uploadModalTitle'
        );

    const fileInput =
        document.getElementById(
            'inspectionFile'
        );


    categoryInput.value =
        category;

    categoryLabel.textContent =
        category;


    modeInput.value =
        isReplace
            ? 'replace'
            : 'upload';


    title.textContent =
        isReplace
            ? 'Replace Inspection Document'
            : 'Upload Inspection Document';


    fileInput.value = '';


    modal.classList.add(
        'show'
    );


    setTimeout(
        function()
        {
            fileInput.click();
        },
        100
    );

}


function closeUploadModal()
{

    const modal =
        document.getElementById(
            'uploadModal'
        );


    if (modal)
    {

        modal.classList.remove(
            'show'
        );

    }

}


function closeUploadModalOnBackdrop(
    event
)
{

    if (
        event.target.id
        ===
        'uploadModal'
    )
    {

        closeUploadModal();

    }

}


document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key
            ===
            'Escape'
        )
        {

            closeUploadModal();

        }

    }
);

</script>


<?php require 'includes/footer.php'; ?>
