<?php
session_start();
session_destroy();
header("Location: host.php");
exit;
?>
