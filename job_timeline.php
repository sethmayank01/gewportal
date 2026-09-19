<?php

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

require 'db.php';
require 'includes/general_config.php';

$jobNo = trim($_GET['job'] ?? '');

if ($jobNo === '') {
    header('Location: status.php');
    exit;
}

$jobStmt = $pdo->prepare("
    SELECT serial_no, data
    FROM jobs
    WHERE serial_no = :serial_no
    LIMIT 1
");
$jobStmt->execute(['serial_no' => $jobNo]);
$job = $jobStmt->fetch();

if (!$job) {
    http_response_code(404);
    die('Job not found.');
}

$jobData = json_decode($job['data'] ?? '', true);

if (!is_array($jobData)) {
    $jobData = [];
}

$historyStmt = $pdo->prepare("
    SELECT
        dpi.id AS process_item_id,
        dpi.process_code,
        dpi.item_code,
        dpi.item_name,
        ds.progress_date,
        ds.status_code,
        ds.planned_status_code,
        ds.remarks,
        ds.updated_by,
        ds.updated_at
    FROM job_process_items dpi
    LEFT JOIN job_process_daily_status ds
        ON ds.job_process_item_id = dpi.id
    WHERE dpi.job_serial_no = :job_serial_no
      AND dpi.active = TRUE
    ORDER BY
        dpi.process_code,
        dpi.item_code,
        ds.progress_date,
        ds.updated_at
");
$historyStmt->execute(['job_serial_no' => $jobNo]);

$items = [];
$historyByDate = [];

foreach ($historyStmt->fetchAll() as $row) {
    $itemId = (int)$row['process_item_id'];

    if (!isset($items[$itemId])) {
        $items[$itemId] = [
            'process_code' => $row['process_code'],
            'item_code' => $row['item_code'],
            'item_name' => $row['item_name'],
            'started_on' => null,
            'completed_on' => null,
            'current_status' => 'NOT_UPDATED',
            'current_date' => null
        ];
    }

    $statusCode = trim($row['status_code'] ?? '');
    $progressDate = $row['progress_date'];

    if ($progressDate === null) {
        continue;
    }

    if (
        in_array($statusCode, ['UNDER_PROCESS', 'COMPLETE'], true)
        && $items[$itemId]['started_on'] === null
    ) {
        $items[$itemId]['started_on'] = $progressDate;
    }

    if (
        $statusCode === 'COMPLETE'
        && $items[$itemId]['completed_on'] === null
    ) {
        $items[$itemId]['completed_on'] = $progressDate;
    }

    if ($statusCode !== '') {
        $items[$itemId]['current_status'] = $statusCode;
        $items[$itemId]['current_date'] = $progressDate;

        $historyByDate[$progressDate][] = [
            'process_code' => $row['process_code'],
            'item_code' => $row['item_code'],
            'item_name' => $row['item_name'],
            'status_code' => $statusCode,
            'remarks' => $row['remarks'],
            'updated_by' => $row['updated_by'],
            'updated_at' => $row['updated_at']
        ];
    }
}

$processPosition = array_flip(array_keys($processDefinitions));

uasort($items, static function ($left, $right) use ($processPosition) {
    $leftPosition = $processPosition[$left['process_code']] ?? PHP_INT_MAX;
    $rightPosition = $processPosition[$right['process_code']] ?? PHP_INT_MAX;

    return [$leftPosition, $left['item_code']]
        <=> [$rightPosition, $right['item_code']];
});

krsort($historyByDate);

$datedItems = array_filter(
    $items,
    static function ($item) {
        return $item['started_on'] !== null;
    }
);

$rangeStart = null;
$rangeEnd = null;
$today = date('Y-m-d');

foreach ($datedItems as $item) {
    $startTimestamp = strtotime($item['started_on']);
    $endTimestamp = strtotime($item['completed_on'] ?? $today);
    $rangeStart = $rangeStart === null ? $startTimestamp : min($rangeStart, $startTimestamp);
    $rangeEnd = $rangeEnd === null ? $endTimestamp : max($rangeEnd, $endTimestamp);
}

$rangeSpan = max(($rangeEnd ?? time()) - ($rangeStart ?? time()), 86400);

function timelineStatusName(string $statusCode, array $processStatuses): string
{
    if ($statusCode === 'NOT_UPDATED') {
        return 'Not Updated';
    }

    return $processStatuses[$statusCode]['name'] ?? $statusCode;
}

function timelineStatusClass(string $statusCode): string
{
    switch ($statusCode) {
        case 'COMPLETE':
            return 'status-success';
        case 'UNDER_PROCESS':
        case 'DRAWING_AVAILABLE':
            return 'status-info';
        case 'HOLD':
        case 'ORDERED':
        case 'ORDER_PENDING':
        case 'DRAWING_PENDING':
            return 'status-warning';
        default:
            return 'status-missing';
    }
}

$pageTitle = $jobNo . ' Activity Timeline';
require 'includes/header.php';

?>

<div class="container job-timeline-page">
    <a class="back" href="status.php">← Back to Production Status</a>

    <div class="card job-timeline-header">
        <div>
            <div class="muted">Job activity timeline</div>
            <h2 class="card-title"><?= htmlspecialchars($jobNo) ?></h2>
            <div class="job-timeline-customer">
                <?= htmlspecialchars($jobData['purchaserName'] ?? '') ?>
                <?php if (!empty($jobData['kva'])): ?>
                    · <?= htmlspecialchars($jobData['kva']) ?> kVA
                <?php endif; ?>
            </div>
        </div>

        <a class="button button-secondary" href="job.php?job=<?= urlencode($jobNo) ?>">
            Open Full Job
        </a>
    </div>

    <div class="card">
        <h3 class="section-title">Activity and Item Durations</h3>

        <?php if (empty($items)): ?>
            <div class="empty">No production activities are configured for this job.</div>
        <?php else: ?>
            <div class="job-item-timeline">
                <?php $lastProcessCode = null; ?>

                <?php foreach ($items as $item): ?>
                    <?php if ($lastProcessCode !== $item['process_code']): ?>
                        <h4 class="job-item-process">
                            <?= htmlspecialchars(
                                $processDefinitions[$item['process_code']]['name']
                                ?? $item['process_code']
                            ) ?>
                        </h4>
                        <?php $lastProcessCode = $item['process_code']; ?>
                    <?php endif; ?>

                    <?php
                    $barLeft = 0;
                    $barWidth = 0;

                    if ($item['started_on'] !== null && $rangeStart !== null) {
                        $itemStart = strtotime($item['started_on']);
                        $itemEnd = strtotime($item['completed_on'] ?? $today);
                        $barLeft = (($itemStart - $rangeStart) / $rangeSpan) * 100;
                        $barWidth = max((($itemEnd - $itemStart) / $rangeSpan) * 100, 1.5);
                        $barWidth = min($barWidth, 100 - $barLeft);
                    }

                    $durationEnd = $item['completed_on'] ?? $today;
                    $durationDays = $item['started_on'] === null
                        ? null
                        : (int)floor(
                            (strtotime($durationEnd) - strtotime($item['started_on'])) / 86400
                        ) + 1;
                    ?>

                    <div class="job-item-row">
                        <div class="job-item-identity">
                            <strong><?= htmlspecialchars($item['item_code']) ?></strong>
                            <span><?= htmlspecialchars($item['item_name']) ?></span>
                        </div>

                        <div class="job-item-dates">
                            <span>
                                Started
                                <strong><?= $item['started_on']
                                    ? htmlspecialchars(date('d M Y', strtotime($item['started_on'])))
                                    : 'Not started'
                                ?></strong>
                            </span>
                            <span>
                                Completed
                                <strong><?= $item['completed_on']
                                    ? htmlspecialchars(date('d M Y', strtotime($item['completed_on'])))
                                    : '—'
                                ?></strong>
                            </span>
                            <?php if ($durationDays !== null): ?>
                                <span>
                                    Duration <strong><?= $durationDays ?> days</strong>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="job-item-track">
                            <?php if ($item['started_on'] !== null): ?>
                                <div
                                    class="job-item-bar <?= $item['completed_on']
                                        ? 'is-complete'
                                        : 'is-progress'
                                    ?>"
                                    style="left: <?= round($barLeft, 2) ?>%; width: <?= round($barWidth, 2) ?>%;"
                                ></div>
                            <?php endif; ?>
                        </div>

                        <span class="status <?= htmlspecialchars(
                            timelineStatusClass($item['current_status'])
                        ) ?>">
                            <?= htmlspecialchars(
                                timelineStatusName($item['current_status'], $processStatuses)
                            ) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3 class="section-title">Date-wise Activity History</h3>

        <?php if (empty($historyByDate)): ?>
            <div class="empty">No dated activity updates are available.</div>
        <?php else: ?>
            <div class="job-history-timeline">
                <?php foreach ($historyByDate as $historyDate => $dateEntries): ?>
                    <section class="job-history-day">
                        <time datetime="<?= htmlspecialchars($historyDate, ENT_QUOTES) ?>">
                            <strong><?= htmlspecialchars(date('d M Y', strtotime($historyDate))) ?></strong>
                            <span><?= htmlspecialchars(date('l', strtotime($historyDate))) ?></span>
                        </time>

                        <div class="job-history-entries">
                            <?php foreach ($dateEntries as $entry): ?>
                                <article class="job-history-entry">
                                    <div class="job-history-entry-heading">
                                        <div>
                                            <span><?= htmlspecialchars(
                                                $processDefinitions[$entry['process_code']]['name']
                                                ?? $entry['process_code']
                                            ) ?></span>
                                            <strong>
                                                <?= htmlspecialchars($entry['item_code']) ?> ·
                                                <?= htmlspecialchars($entry['item_name']) ?>
                                            </strong>
                                        </div>
                                        <span class="status <?= htmlspecialchars(
                                            timelineStatusClass($entry['status_code'])
                                        ) ?>">
                                            <?= htmlspecialchars(
                                                timelineStatusName($entry['status_code'], $processStatuses)
                                            ) ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($entry['remarks'])): ?>
                                        <div class="job-history-remarks">
                                            <?= nl2br(htmlspecialchars($entry['remarks'])) ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="job-history-meta">
                                        Updated by <?= htmlspecialchars($entry['updated_by'] ?? '—') ?>
                                        <?php if (!empty($entry['updated_at'])): ?>
                                            at <?= htmlspecialchars(date('h:i A', strtotime($entry['updated_at']))) ?>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
