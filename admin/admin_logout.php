<?php
session_start();
unset($_SESSION['admin_id'], $_SESSION['admin_full_name']);
session_regenerate_id(true);
header('Location: admin_login.php');
exit;