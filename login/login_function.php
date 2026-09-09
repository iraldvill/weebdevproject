<?php
session_start();
require '../database/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$genericError = 'Incorrect email or password.';

if ($email === '' || $password === '') {
    $_SESSION['login_error'] = $genericError;
    $_SESSION['old_email']   = $email;
    header('Location: login.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, full_name, email, password_hash FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = $genericError;
    $_SESSION['old_email']   = $email;
    header('Location: login.php');
    exit;
}

session_regenerate_id(true);

$_SESSION['user_id']    = $user['id'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['email']      = $user['email'];


if (!empty($_SESSION['redirect_after_login'])) {
    $target = $_SESSION['redirect_after_login'];
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $target);
} else {
    header('Location: ../account/my_bookings.php');
}
exit;