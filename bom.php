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

$stmt = $pdo->prepare("
    SELECT serial_no, data
    FROM jobs
    WHERE serial_no = :serial_no
    LIMIT 1
");
$stmt->execute(['serial_no' => $jobNo]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    exit('Job not found.');
}

$jobData = [];

if (!empty($job['data'])) {
    $decoded = json_decode($job['data'], true);
    if (is_array($decoded)) {
        $jobData = $decoded;
    }
}

/*
|--------------------------------------------------------------------------
| Common job_header.php expects $data
|--------------------------------------------------------------------------
*/
$data = $jobData;


/*
|--------------------------------------------------------------------------
| Material category normalization
|--------------------------------------------------------------------------
*/
$categoryMap = [
    'CCA'       => 'CCA',
    'TANKING'   => 'TANKING',
    'MOUNTING'  => 'MOUNTING',
    'ACC'       => 'ACCESSORY',
    'ACCESSORY' => 'ACCESSORY',
    'SUNDRY'    => 'SUNDRY'
];

$displayCategories = array_values(
    array_unique(array_values($categoryMap))
);

$selectedCategories = $_GET['category'] ?? [];

if (!is_array($selectedCategories)) {
    $selectedCategories = [$selectedCategories];
}

$selectedCategories = array_values(
    array_intersect($selectedCategories, $displayCategories)
);

$search = trim($_GET['search'] ?? '');
$materials = [];

if (!empty($selectedCategories)) {

    $dbCategories = [];

    foreach ($categoryMap as $dbCategory => $displayCategory) {
        if (in_array($displayCategory, $selectedCategories, true)) {
            $dbCategories[] = $dbCategory;
        }
    }

    $dbCategories = array_values(array_unique($dbCategories));

    $placeholders = [];
    $params = [];

    foreach ($dbCategories as $i => $category) {
        $key = ':cat' . $i;
        $placeholders[] = $key;
        $params[$key] = $category;
    }

    $conditions = [
        "UPPER(TRIM(data::jsonb->>'category')) IN (" .
        implode(',', $placeholders) . ")"
    ];

    if ($search !== '') {
        $conditions[] = "
            (
                type ILIKE :search
                OR subtype ILIKE :search
                OR CONCAT(type, ' ', subtype) ILIKE :search
            )
        ";

        $params[':search'] = '%' . $search . '%';
    }

    $sql = "
        SELECT
            id,
            type,
            subtype,
            data::jsonb->>'unit' AS unit,
            data::jsonb->>'category' AS category
        FROM materials
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY type, subtype
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();
}

function displayCategory(
    string $category,
    array $categoryMap
): string {
    $normalized = strtoupper(trim($category));
    return $categoryMap[$normalized] ?? $category;
}

$pageTitle = 'BOM - ' . $jobNo;

require 'includes/header.php';

?>

<div class="container">

    <a
        class="back"
        href="job.php?job=<?= urlencode($jobNo) ?>"
    >
        ← Back to Job
    </a>

    <?php require 'includes/job_header.php'; ?>


    <div class="card">

        <h2 class="card-title">
            Select Material Categories
        </h2>

        <form
            method="GET"
            action="bom.php"
            id="categoryForm"
        >

            <input
                type="hidden"
                name="job"
                value="<?= htmlspecialchars($jobNo) ?>"
            >

            <div class="category-grid">

                <?php foreach ($displayCategories as $category): ?>

                    <label class="category-item">

                        <input
                            type="checkbox"
                            name="category[]"
                            value="<?= htmlspecialchars($category) ?>"
                            <?= in_array(
                                $category,
                                $selectedCategories,
                                true
                            ) ? 'checked' : '' ?>
                            onchange="this.form.submit();"
                        >

                        <span class="category-name">
                            <?= htmlspecialchars($category) ?>
                        </span>

                    </label>

                <?php endforeach; ?>

            </div>

        </form>

    </div>


    <div class="card">

        <h2 class="card-title">
            Materials
        </h2>

        <?php if (empty($selectedCategories)): ?>

            <div class="empty">
                Select one or more categories above to display
                the available materials.
            </div>

        <?php else: ?>

            <form method="GET" action="bom.php">

                <input
                    type="hidden"
                    name="job"
                    value="<?= htmlspecialchars($jobNo) ?>"
                >

                <?php foreach ($selectedCategories as $category): ?>

                    <input
                        type="hidden"
                        name="category[]"
                        value="<?= htmlspecialchars($category) ?>"
                    >

                <?php endforeach; ?>

                <div class="toolbar">

                    <div class="search-box">

                        <input
                            type="text"
                            id="materialSearch"
                            placeholder="Search material, type or subtype..."
                            autocomplete="off"
                        >

                    </div>

                    <button
                        type="button"
                        class="button button-secondary"
                        onclick="clearMaterialSearch()"
                    >
                        Clear
                    </button>

                </div>

                <div class="material-count">
                    <span id="materialCount">
                        <?= count($materials) ?>
                    </span>
                    materials found
                </div>

                <?php if (empty($materials)): ?>

                    <div class="empty">
                        No materials found for the selected category/search.
                    </div>

                <?php else: ?>

                    <div class="table-container">

                        <table class="bom-table">

                            <thead>

                                <tr>

                                    <th class="bom-select-column">
                                        Select
                                    </th>

                                    <th>Material</th>
                                    <th>Category</th>
                                    <th>Unit</th>

                                </tr>

                            </thead>

                            <tbody>

                            <?php foreach ($materials as $material): ?>

                                <tr
                                    class="material-row"
                                    data-search="<?= htmlspecialchars(
                                        strtolower(
                                            ($material['type'] ?? '') . ' ' .
                                            ($material['subtype'] ?? '') . ' ' .
                                            ($material['category'] ?? '') . ' ' .
                                            ($material['unit'] ?? '')
                                        ),
                                        ENT_QUOTES
                                    ) ?>"
                                >

                                    <td>
                                        <input
                                            type="checkbox"
                                            name="material_id[]"
                                            value="<?= htmlspecialchars($material['id']) ?>"
                                        >
                                    </td>

                                    <td>

                                        <strong>
                                            <?= htmlspecialchars($material['type']) ?>
                                        </strong>

                                        <?php if (!empty($material['subtype'])): ?>

                                            <br>

                                            <span class="material-subtype">
                                                <?= htmlspecialchars($material['subtype']) ?>
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            displayCategory(
                                                $material['category'] ?? '',
                                                $categoryMap
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $material['unit'] ?? ''
                                        ) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <div class="bottom-bar">

                        <div class="selected-count">
                            <span id="selectedCount">0</span>
                            material(s) selected
                        </div>

                        <button
                            type="button"
                            class="button button-primary"
                            onclick="addSelected()"
                        >
                            Add Selected
                        </button>

                    </div>

                <?php endif; ?>

            </form>

        <?php endif; ?>

    </div>

</div>


<script>

function updateSelectedCount() {

    const checked = document.querySelectorAll(
        'input[name="material_id[]"]:checked'
    );

    const counter =
        document.getElementById('selectedCount');

    if (counter) {
        counter.textContent = checked.length;
    }
}


function updateMaterialCount() {

    const rows =
        document.querySelectorAll('.material-row');

    let visibleCount = 0;

    rows.forEach(function(row) {

        if (row.style.display !== 'none') {
            visibleCount++;
        }

    });

    const counter =
        document.getElementById('materialCount');

    if (counter) {
        counter.textContent = visibleCount;
    }
}


function filterMaterials() {

    const searchInput =
        document.getElementById('materialSearch');

    if (!searchInput) {
        return;
    }

    const searchText =
        searchInput.value.trim().toLowerCase();

    const searchWords =
        searchText
            .split(/\s+/)
            .filter(word => word.length > 0);

    const rows =
        document.querySelectorAll('.material-row');

    rows.forEach(function(row) {

        const searchableText =
            (row.dataset.search || '').toLowerCase();

        const matches =
            searchWords.every(function(word) {
                return searchableText.includes(word);
            });

        row.style.display =
            (searchWords.length === 0 || matches)
                ? ''
                : 'none';

    });

    updateMaterialCount();
}


const materialSearch =
    document.getElementById('materialSearch');

if (materialSearch) {

    materialSearch.addEventListener(
        'input',
        filterMaterials
    );

}


document.addEventListener(
    'change',
    function(event) {

        if (
            event.target.matches(
                'input[name="material_id[]"]'
            )
        ) {
            updateSelectedCount();
        }

    }
);


function addSelected() {

    const checked =
        document.querySelectorAll(
            'input[name="material_id[]"]:checked'
        );

    if (checked.length === 0) {

        alert(
            'Please select at least one material.'
        );

        return;
    }

    alert(
        checked.length +
        ' material(s) selected. BOM saving will be added next.'
    );
}


function clearMaterialSearch() {

    const searchInput =
        document.getElementById('materialSearch');

    if (searchInput) {

        searchInput.value = '';

        filterMaterials();

        searchInput.focus();

    }

}


updateSelectedCount();
updateMaterialCount();
filterMaterials();

</script>


<?php require 'includes/footer.php'; ?>
