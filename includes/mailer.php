<?php

/*
|--------------------------------------------------------------------------
| GEW Portal Mailer
|--------------------------------------------------------------------------
|
| Common email utility for portal notifications.
|
*/


/*
|--------------------------------------------------------------------------
| PHPMailer
|--------------------------------------------------------------------------
|
| Assumes PHPMailer is installed through Composer:
|
| composer require phpmailer/phpmailer
|
*/

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__
    . '/../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/*
|--------------------------------------------------------------------------
| Email Configuration
|--------------------------------------------------------------------------
*/

$mailConfig = [

    /*
     * SMTP Server
     */

    'host' =>
        'smtp.zoho.com',

    'port' =>
        587,

    'encryption' =>
        PHPMailer::ENCRYPTION_STARTTLS,


    /*
     * SMTP Login
     */

    'username' =>
        'portal@geworks.co.in',

    'password' =>
        'Portal@211010',


    /*
     * Sender
     */

    'from_email' =>
        'portal@geworks.co.in',

    'from_name' =>
        'GEW Engineering Portal',


    /*
     * Portal URL
     */

    'portal_url' =>
        'http://192.168.2.205/gewportal',


    /*
     * Notification Recipients
     *
     * Every drawing notification will
     * be sent to these email addresses.
     */

    'recipients' => [

        'services@geworks.co.in',
        'deepjyoti.karmakar@geworks.co.in',
        'sunil.singh@geworks.co.in',
		'aditya.goswami@geworks.co.in'

    ]

];


/*
|--------------------------------------------------------------------------
| Generic Portal Email
|--------------------------------------------------------------------------
*/

function sendPortalEmail(
    array $recipients,
    string $subject,
    string $htmlBody
): bool {

    global $mailConfig;


    $mail =
        new PHPMailer(true);


    /*
    |--------------------------------------------------------------------------
    | SMTP
    |--------------------------------------------------------------------------
    */

    $mail->isSMTP();

    $mail->Host =
        $mailConfig['host'];

    $mail->SMTPAuth =
        true;

    $mail->Username =
        $mailConfig['username'];

    $mail->Password =
        $mailConfig['password'];

    $mail->SMTPSecure =
        $mailConfig['encryption'];

    $mail->Port =
        $mailConfig['port'];


    /*
    |--------------------------------------------------------------------------
    | Sender
    |--------------------------------------------------------------------------
    */

    $mail->setFrom(
        $mailConfig['from_email'],
        $mailConfig['from_name']
    );


    /*
    |--------------------------------------------------------------------------
    | Recipients
    |--------------------------------------------------------------------------
    */

    foreach (
        $recipients
        as $recipient
    ) {

        $recipient =
            trim($recipient);

        if (
            $recipient !== ''
            &&
            filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $mail->addAddress(
                $recipient
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Email Format
    |--------------------------------------------------------------------------
    */

    $mail->isHTML(true);

    $mail->CharSet =
        'UTF-8';

    $mail->Subject =
        $subject;

    $mail->Body =
        $htmlBody;

    $mail->AltBody =
        strip_tags(
            str_replace(
                [
                    '<br>',
                    '<br/>',
                    '<br />'
                ],
                "\n",
                $htmlBody
            )
        );


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    return $mail->send();

}



/*
|--------------------------------------------------------------------------
| Drawing Revision Notification
|--------------------------------------------------------------------------
*/

function sendDrawingRevisionNotification(
    PDO $pdo,
    int $drawingId,
    string $revision
): bool {

    global $mailConfig;


    /*
    |--------------------------------------------------------------------------
    | Get Drawing + Revision + Job Information
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("
            SELECT

                d.id,
                d.job_serial_no,
                d.section,
                d.drawing_no,
                d.title,

                dr.revision,
                dr.change_description,
                dr.uploaded_by,
                dr.uploaded_at AS created_at,

                j.data AS job_data

            FROM drawings d

            INNER JOIN drawing_revision dr
                ON dr.drawing_id = d.id
               AND dr.revision = :revision

            LEFT JOIN jobs j
                ON j.serial_no = d.job_serial_no

            WHERE
                d.id = :drawing_id

            LIMIT 1
        ");


    $stmt->execute([

        'drawing_id' =>
            $drawingId,

        'revision' =>
            $revision

    ]);


    $drawing =
        $stmt->fetch();


    if (!$drawing) {

        throw new RuntimeException(
            'Unable to find drawing information '
            . 'for email notification.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Decode Job Data
    |--------------------------------------------------------------------------
    */

    $jobData =
        json_decode(
            $drawing['job_data'] ?? '',
            true
        );


    if (!is_array($jobData)) {

        $jobData = [];

    }


    $purchaser =
        $jobData[
            'purchaserName'
        ] ?? '';


    $capacity =
        $jobData[
            'kva'
        ] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Safe HTML Values
    |--------------------------------------------------------------------------
    */

    $jobNo =
        htmlspecialchars(
            $drawing['job_serial_no']
        );

    $section =
        htmlspecialchars(
            $drawing['section']
        );

    $drawingNo =
        htmlspecialchars(
            $drawing['drawing_no']
        );

    $title =
        htmlspecialchars(
            $drawing['title']
        );

    $revisionText =
        htmlspecialchars(
            $drawing['revision']
        );

    $purchaserText =
        htmlspecialchars(
            $purchaser
        );

    $capacityText =
        htmlspecialchars(
            $capacity
        );

    $uploadedBy =
        htmlspecialchars(
            $drawing['uploaded_by']
            ?? ''
        );

    $changeDescription =
        htmlspecialchars(
            $drawing[
                'change_description'
            ] ?? ''
        );


    /*
    |--------------------------------------------------------------------------
    | Date
    |--------------------------------------------------------------------------
    */

    $uploadedDate = '';

    if (
        !empty(
            $drawing['created_at']
        )
    ) {

        $uploadedDate =
            date(
                'd-M-Y h:i A',
                strtotime(
                    $drawing['created_at']
                )
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Drawing URL
    |--------------------------------------------------------------------------
    */

    $portalUrl =
        rtrim(
            $mailConfig[
                'portal_url'
            ],
            '/'
        );


    $drawingUrl =
        $portalUrl
        . '/drawing.php?id='
        . $drawingId;


    /*
    |--------------------------------------------------------------------------
    | Determine New Drawing / Revision
    |--------------------------------------------------------------------------
    */

    $isInitialRevision =
        strcasecmp(
            $revision,
            'Rev-00'
        ) === 0;


    if ($isInitialRevision) {

        $subject =
            'New Drawing Available - '
            . $drawing['job_serial_no']
            . ' - '
            . $drawing['drawing_no'];

        $heading =
            'New Drawing Available';

    }
    else {

        $subject =
            'Drawing Revised - '
            . $drawing['job_serial_no']
            . ' - '
            . $drawing['drawing_no']
            . ' - '
            . $revision;

        $heading =
            'Drawing Revision Available';

    }


    /*
    |--------------------------------------------------------------------------
    | Email Body
    |--------------------------------------------------------------------------
    */

    $htmlBody = '

    <div style="
        font-family:Arial,sans-serif;
        max-width:700px;
        color:#222;
    ">

        <div style="
            background:#174a7e;
            color:white;
            padding:16px 20px;
            font-size:20px;
            font-weight:bold;
        ">
            GEW Transformer Engineering Portal
        </div>


        <div style="
            border:1px solid #ddd;
            border-top:none;
            padding:20px;
        ">

            <h2 style="
                color:#174a7e;
                margin-top:0;
            ">
                ' . $heading . '
            </h2>


            <table
                cellpadding="7"
                cellspacing="0"
                style="
                    border-collapse:collapse;
                    width:100%;
                    font-size:14px;
                "
            >

                <tr>
                    <td style="
                        width:160px;
                        color:#666;
                    ">
                        Job No.
                    </td>

                    <td>
                        <strong>
                            ' . $jobNo . '
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Purchaser
                    </td>

                    <td>
                        ' . $purchaserText . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Capacity
                    </td>

                    <td>
                        ' . $capacityText . ' kVA
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Section
                    </td>

                    <td>
                        ' . $section . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Drawing No.
                    </td>

                    <td>
                        <strong>
                            ' . $drawingNo . '
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Drawing Title
                    </td>

                    <td>
                        ' . $title . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Revision
                    </td>

                    <td>
                        <strong>
                            ' . $revisionText . '
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Change Description
                    </td>

                    <td>
                        ' . (
                            $changeDescription !== ''
                                ? $changeDescription
                                : '—'
                        ) . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Uploaded By
                    </td>

                    <td>
                        ' . $uploadedBy . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Uploaded On
                    </td>

                    <td>
                        ' . $uploadedDate . '
                    </td>
                </tr>

            </table>


            <div style="
                margin-top:22px;
            ">

                <a
                    href="' . htmlspecialchars(
                        $drawingUrl
                    ) . '"
                    style="
                        display:inline-block;
                        background:#174a7e;
                        color:white;
                        text-decoration:none;
                        padding:10px 18px;
                        border-radius:5px;
                        font-weight:bold;
                    "
                >
                    View Drawing
                </a>

            </div>


            <div style="
                margin-top:25px;
                padding-top:15px;
                border-top:1px solid #eee;
                color:#888;
                font-size:12px;
            ">

                This is an automated notification
                from the GEW Transformer Engineering Portal.

            </div>

        </div>

    </div>

    ';


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    return sendPortalEmail(
        $mailConfig['recipients'],
        $subject,
        $htmlBody
    );
	
	

}

/*
|--------------------------------------------------------------------------
| Inspection Document Notification
|--------------------------------------------------------------------------
*/

function sendInspectionNotification(
    PDO $pdo,
    string $jobNo,
    string $category,
    string $mode
): bool {

    global $mailConfig;


    /*
    |--------------------------------------------------------------------------
    | Get Inspection Document + Job Information
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            i.id,
            i.job_serial_no,
            i.category,
            i.file_name,
            i.uploaded_by,
            i.created_at,
            i.updated_by,
            i.updated_at,

            j.data AS job_data

        FROM inspection_documents i

        LEFT JOIN jobs j
            ON j.serial_no = i.job_serial_no

        WHERE
            i.job_serial_no = :job_serial_no
            AND
            i.category = :category

        LIMIT 1
    ");


    $stmt->execute([

        'job_serial_no' =>
            $jobNo,

        'category' =>
            $category

    ]);


    $document =
        $stmt->fetch();


    if (!$document) {

        throw new RuntimeException(
            'Unable to find inspection document '
            . 'for email notification.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Job Data
    |--------------------------------------------------------------------------
    */

    $jobData =
        json_decode(
            $document['job_data'] ?? '',
            true
        );

    if (!is_array($jobData)) {
        $jobData = [];
    }


    $purchaser =
        $jobData['purchaserName']
        ?? '';

    $capacity =
        $jobData['kva']
        ?? '';


    /*
    |--------------------------------------------------------------------------
    | Upload / Replacement Details
    |--------------------------------------------------------------------------
    */

    $isReplace =
        ($mode === 'replace');


    if ($isReplace) {

        $heading =
            'Inspection Document Updated';

        $subject =
            'Inspection Document Updated - '
            . $jobNo
            . ' - '
            . $category;

        $user =
            $document['updated_by']
            ?: $document['uploaded_by'];

        $dateValue =
            $document['updated_at']
            ?: $document['created_at'];

    }
    else {

        $heading =
            'New Inspection Document Available';

        $subject =
            'New Inspection Document - '
            . $jobNo
            . ' - '
            . $category;

        $user =
            $document['uploaded_by'];

        $dateValue =
            $document['created_at'];

    }


    $displayDate = '';

    if (!empty($dateValue)) {

        $displayDate =
            date(
                'd-M-Y h:i A',
                strtotime($dateValue)
            );

    }


    /*
    |--------------------------------------------------------------------------
    | Safe HTML Values
    |--------------------------------------------------------------------------
    */

    $jobNoHtml =
        htmlspecialchars($jobNo);

    $purchaserHtml =
        htmlspecialchars($purchaser);

    $capacityHtml =
        htmlspecialchars($capacity);

    $categoryHtml =
        htmlspecialchars($category);

    $fileNameHtml =
        htmlspecialchars(
            $document['file_name'] ?? ''
        );

    $userHtml =
        htmlspecialchars($user ?? '');

    $dateHtml =
        htmlspecialchars($displayDate);


    /*
    |--------------------------------------------------------------------------
    | Inspection Page URL
    |--------------------------------------------------------------------------
    */

    $portalUrl =
        rtrim(
            $mailConfig['portal_url'],
            '/'
        );


    $inspectionUrl =
        $portalUrl
        . '/inspection.php?job='
        . urlencode($jobNo);


    /*
    |--------------------------------------------------------------------------
    | Email Body
    |--------------------------------------------------------------------------
    */

    $htmlBody = '

    <div style="
        font-family:Arial,sans-serif;
        max-width:700px;
        color:#222;
    ">

        <div style="
            background:#174a7e;
            color:white;
            padding:16px 20px;
            font-size:20px;
            font-weight:bold;
        ">
            GEW Transformer Engineering Portal
        </div>


        <div style="
            border:1px solid #ddd;
            border-top:none;
            padding:20px;
        ">

            <h2 style="
                color:#174a7e;
                margin-top:0;
            ">
                ' . $heading . '
            </h2>


            <table
                cellpadding="7"
                cellspacing="0"
                style="
                    width:100%;
                    border-collapse:collapse;
                    font-size:14px;
                "
            >

                <tr>
                    <td style="
                        width:160px;
                        color:#666;
                    ">
                        Job No.
                    </td>

                    <td>
                        <strong>
                            ' . $jobNoHtml . '
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Purchaser
                    </td>

                    <td>
                        ' . $purchaserHtml . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Capacity
                    </td>

                    <td>
                        ' . $capacityHtml . ' kVA
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Inspection Document
                    </td>

                    <td>
                        <strong>
                            ' . $categoryHtml . '
                        </strong>
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        File
                    </td>

                    <td>
                        ' . $fileNameHtml . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        ' . (
                            $isReplace
                                ? 'Updated By'
                                : 'Uploaded By'
                        ) . '
                    </td>

                    <td>
                        ' . $userHtml . '
                    </td>
                </tr>


                <tr>
                    <td style="color:#666;">
                        Date
                    </td>

                    <td>
                        ' . $dateHtml . '
                    </td>
                </tr>

            </table>


            <div style="
                margin-top:22px;
            ">

                <a
                    href="' . htmlspecialchars(
                        $inspectionUrl
                    ) . '"
                    style="
                        display:inline-block;
                        background:#174a7e;
                        color:white;
                        text-decoration:none;
                        padding:10px 18px;
                        border-radius:5px;
                        font-weight:bold;
                    "
                >
                    View Inspection Documents
                </a>

            </div>


            <div style="
                margin-top:25px;
                padding-top:15px;
                border-top:1px solid #eee;
                color:#888;
                font-size:12px;
            ">

                This is an automated notification
                from the GEW Transformer Engineering Portal.

            </div>

        </div>

    </div>

    ';


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    return sendPortalEmail(
        $mailConfig['recipients'],
        $subject,
        $htmlBody
    );

}
