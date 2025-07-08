<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pdo = new PDO("mysql:host=localhost;dbname=esginlcb_everskies;charset=utf8mb4", "esginlcb_dan", "m9yEES4EM3F8mRpc");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>
