<?php
session_start();
$_SESSION = [];
session_destroy();
header('Location: ../homepage/index.php');
exit;