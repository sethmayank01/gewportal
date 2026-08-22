<?php

session_start();

$config = require 'config.php';
require 'db.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter username and password.';

    } else {

        $sql = "
            SELECT username, password, role
            FROM users
            WHERE username = :username
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'username' => $username
        ]);

        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['user'] = [
                'username' => $user['username'],
                'role'     => $user['role']
            ];

            header('Location: jobs.php');
            exit;

        } else {

            $error = 'Invalid username or password.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>GEW Transformer Portal</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

</head>

<body>

<div class="login-page">

    <div class="login-card">

        <h1 class="login-title">
            GEW
        </h1>

        <div class="subtitle">
            Transformer Engineering Portal
        </div>


        <?php if (!empty($error)): ?>

            <div class="error-box">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">

            <div class="form-group">

                <label class="form-label">
                    Username
                </label>

                <input
                    type="text"
                    name="username"
                    class="form-control"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label class="form-label">
                    Password
                </label>

                <input
                    type="password"
                    name="password"
                    class="form-control"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="button button-primary button-large login-button"
            >
                Login
            </button>

        </form>

    </div>

</div>

</body>

</html>