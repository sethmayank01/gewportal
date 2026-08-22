<?php

/*
|--------------------------------------------------------------------------
| Common Job Header
|--------------------------------------------------------------------------
|
| Expected variables:
|
|   $jobNo
|   $data
|
| Example:
|
|   TRFD0731
|   DRM Office RANCHI DIVISION SER | 25 kVA
|
*/

if (!isset($jobNo)) {
    $jobNo = '';
}

if (!isset($data) || !is_array($data)) {
    $data = [];
}

?>

<div class="job-header">

    <div class="job-header-content">

        <div class="job-title">

            <?= htmlspecialchars($jobNo) ?>

        </div>


        <div class="job-subtitle">

            <?= htmlspecialchars(
                $data['purchaserName'] ?? ''
            ) ?>

            <?php if (
                !empty($data['kva'])
            ): ?>

                |
                <?= htmlspecialchars(
                    $data['kva']
                ) ?>
                kVA

            <?php endif; ?>

        </div>

    </div>

</div>