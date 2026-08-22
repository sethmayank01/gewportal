<?php

$config = require 'config.php';

$dsn = "pgsql:host={$config['db']['host']};port={$config['db']['port']};dbname={$config['db']['name']}";

try {

    $pdo = new PDO(
        $dsn,
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $pdo->exec("SET TIME ZONE 'Asia/Kolkata'");

} catch (PDOException $e) {

    die("Database connection failed.");

}