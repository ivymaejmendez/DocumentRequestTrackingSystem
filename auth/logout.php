<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: /document-request-system/auth/login.php');
exit;
