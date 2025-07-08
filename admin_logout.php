<?php
// 4. CREATE admin_logout.php
session_start();
session_destroy();
header("Location: admin.php");
exit;
?>