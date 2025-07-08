<?php
include 'db.php';

if (!isset($_SESSION['host_id'])) {
  header("Location: host.php");
  exit;
}
?>