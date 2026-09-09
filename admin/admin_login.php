<?php

session_start();
require '../database/config.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: admin.php');
    exit;
}

$loginError = '';
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $oldEmail = $email;

    $stmt = $pdo->prepare('SELECT id, full_name, password_hash FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']        = $admin['id'];
        $_SESSION['admin_full_name'] = $admin['full_name'];
        header('Location: admin.php');
        exit;
    }

    $loginError = 'Incorrect email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Log In — Villaflores Gaming Cafe</title>
<link rel="stylesheet" href="../style.css">
<style>
    .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
    .auth-card { width: 100%; max-width: 420px; border-radius: 16px; border: 1px solid var(--border); background-color: var(--card); padding: 40px; }
    .auth-title { font-family: var(--font-display); font-size: 28px; font-weight: 800; text-transform: uppercase; }
    .auth-sub { margin-top: 8px; font-size: 14px; color: var(--muted-foreground); }
    .auth-field { margin-top: 20px; display: flex; flex-direction: column; gap: 6px; }
    .auth-field label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted-foreground); }
    .auth-field input { background-color: var(--muted); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; color: var(--foreground); font-family: var(--font-body); font-size: 14px; }
    .auth-field input:focus { outline: none; border-color: var(--cerulean); }
    .auth-submit { margin-top: 28px; width: 100%; border: none; border-radius: 6px; padding: 14px 0; font-family: var(--font-display); font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; background-color: var(--cerulean); color: #000000; cursor: pointer; }
    .auth-errors { margin-top: 20px; border-radius: 8px; border: 1px solid var(--pink); background-color: rgba(246,4,126,0.08); padding: 14px 16px; }
    .auth-errors p { font-size: 13px; color: var(--pink); margin: 0; }
    .auth-footer { margin-top: 20px; font-size: 13px; color: var(--muted-foreground); text-align: center; }
</style>
</head>
<body>
<div class="auth-wrap brand-grid">
    <div class="auth-card">
        <h1 class="auth-title">Admin Login</h1>
        <p class="auth-sub">Villaflores Gaming Cafe — staff access only.</p>

        <?php if ($loginError): ?>
            <div class="auth-errors"><p><?php echo htmlspecialchars($loginError); ?></p></div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php" novalidate>
            <div class="auth-field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($oldEmail); ?>" required>
            </div>
            <div class="auth-field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="auth-submit">Log In</button>
        </form>

        <p class="auth-footer">← <a href="../homepage/index.php" style="color: var(--cerulean); text-decoration:none;">Back to homepage</a></p>
    </div>
</div>
</body>
</html>