<?php
// 3. CREATE admin_auth.php
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin.php");
    exit;
}
?>