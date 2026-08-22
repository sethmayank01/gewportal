<?php

/*
|--------------------------------------------------------------------------
| Common GEW Portal Header
|--------------------------------------------------------------------------
|
| Expected variables:
|
|   $pageTitle = 'Page Title';
|
| The calling PHP file remains responsible for:
|   - session_start()
|   - authentication / access control
|   - database work
|
*/

$pageTitle = $pageTitle
    ?? 'GEW Transformer Engineering Portal';

$username = $_SESSION['user']['username']
    ?? '';

?>
<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        <?= htmlspecialchars($pageTitle) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="portal-header">

    <div class="portal-title">

        GEW Transformer Engineering Portal

    </div>

    <div class="portal-user">

        <span>
            User:
            <?= htmlspecialchars($username) ?>
        </span>

        <span class="portal-separator">
            |
        </span>

        <a
            href="logout.php"
            class="portal-logout"
        >
            Logout
        </a>

    </div>

</div>
