<?php
/**
 * ONE-TIME USE ONLY.
 * Creates the first admin account, then DELETE THIS FILE.
 * Leaving it live means anyone who finds the URL can create
 * their own admin account.
 *
 * Place at: /admin/setup_admin.php
 */

session_start();
require '../database/config.php';

$errors = [];
$success = false;
$old = ['full_name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    $old['full_name'] = $full_name;
    $old['email']     = $email;

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }
    if (strlen($password) < 8 || strlen($password) > 64) {
        $errors[] = 'Password must be between 8 and 64 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE email = ?');
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $errors[] = 'An admin with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $insert = $pdo->prepare(
                'INSERT INTO admins (full_name, email, password_hash) VALUES (?, ?, ?)'
            );
            $insert->execute([$full_name, $email, $hash]);
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Set Up Admin — Villaflores Gaming Cafe</title>
<link rel="stylesheet" href="../style.css">
<style>
    .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; }
    .auth-card { width: 100%; max-width: 420px; border-radius: 16px; border: 1px solid var(--border); background-color: var(--card); padding: 40px; }
    .auth-title { font-family: var(--font-display); font-size: 26px; font-weight: 800; text-transform: uppercase; }
    .auth-sub { margin-top: 8px; font-size: 14px; color: var(--muted-foreground); }
    .auth-field { margin-top: 20px; display: flex; flex-direction: column; gap: 6px; }
    .auth-field label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted-foreground); }
    .auth-field input { background-color: var(--muted); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; color: var(--foreground); font-family: var(--font-body); font-size: 14px; }
    .auth-field input:focus { outline: none; border-color: var(--cerulean); }
    .auth-submit { margin-top: 28px; width: 100%; border: none; border-radius: 6px; padding: 14px 0; font-family: var(--font-display); font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; background-color: var(--cerulean); color: #000000; cursor: pointer; }
    .auth-errors { margin-top: 20px; border-radius: 8px; border: 1px solid var(--pink); background-color: rgba(246,4,126,0.08); padding: 14px 16px; }
    .auth-errors p { font-size: 13px; color: var(--pink); margin: 4px 0; }
    .auth-success { margin-top: 20px; border-radius: 8px; border: 1px solid var(--cerulean); background-color: rgba(0,161,245,0.08); padding: 14px 16px; }
    .auth-success p { font-size: 13px; color: var(--cerulean); margin: 4px 0; }
    .warn { margin-top: 20px; font-size: 12px; color: var(--pink); text-align: center; }
</style>
</head>
<body>
<div class="auth-wrap brand-grid">
    <div class="auth-card">
        <h1 class="auth-title">Create Admin Account</h1>
        <p class="auth-sub">One-time setup. Delete this file once you're done.</p>

        <?php if ($success): ?>
            <div class="auth-success"><p>Admin account created. You can now <a href="admin_login.php" style="color:var(--cerulean);">log in</a>.</p></div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="auth-errors">
                    <?php foreach ($errors as $error): ?><p>• <?php echo htmlspecialchars($error); ?></p><?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="setup_admin.php" novalidate>
                <div class="auth-field">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($old['full_name']); ?>" required>
                </div>
                <div class="auth-field">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($old['email']); ?>" required>
                </div>
                <div class="auth-field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" minlength="8" maxlength="64" required>
                </div>
                <div class="auth-field">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="8" maxlength="64" required>
                </div>
                <button type="submit" class="auth-submit">Create Admin</button>
            </form>
        <?php endif; ?>

        <p class="warn">⚠ Delete setup_admin.php from your server after creating your account.</p>
    </div>
</div>
</body>
</html>