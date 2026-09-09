<?php

session_start();
require '../database/config.php';

if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

function h($val) {
    return htmlspecialchars($val ?? "", ENT_QUOTES, "UTF-8");
}

$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_booking') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($bookingId > 0) {
        $stmt = $pdo->prepare('DELETE FROM bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $_SESSION['admin_flash'] = $stmt->rowCount() > 0
            ? "Booking #$bookingId deleted."
            : "Booking #$bookingId was already gone.";
    }

    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_booking_status') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if ($bookingId > 0 && in_array($newStatus, ['confirmed', 'cancelled', 'pending'], true)) {
        $stmt = $pdo->prepare('UPDATE bookings SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $bookingId]);
        $_SESSION['admin_flash'] = "Booking #$bookingId marked as $newStatus.";
    }

    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $_SESSION['admin_flash'] = $stmt->rowCount() > 0
            ? "Account #$userId deleted (their bookings and reviews were removed too)."
            : "Account #$userId was already gone.";
    }

    header('Location: admin.php');
    exit;
}

if (!empty($_SESSION['admin_flash'])) {
    $flash = $_SESSION['admin_flash'];
    unset($_SESSION['admin_flash']);
}

$error = null;
$users = [];
$bookings = [];
$stats = ["total_users" => 0, "total_bookings" => 0, "confirmed_bookings" => 0, "today_bookings" => 0];

try {
    // --- users 
    $users = $pdo->query(
        "SELECT id, full_name, email, created_at FROM users ORDER BY created_at DESC"
    )->fetchAll();
    $stats["total_users"] = count($users);

    // --- bookings 
    $bookings = $pdo->query(
        "SELECT b.id, b.user_id, u.full_name, u.email,
                b.plan_name, b.booking_date, b.booking_time, b.stations,
                b.amount, b.payment_method, b.payment_status, b.status, b.created_at
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         ORDER BY b.booking_date DESC, b.booking_time DESC"
    )->fetchAll();

    $stats["total_bookings"] = count($bookings);
    foreach ($bookings as $b) {
        if ($b["status"] === "confirmed") {
            $stats["confirmed_bookings"]++;
        }
        if ($b["booking_date"] === date("Y-m-d")) {
            $stats["today_bookings"]++;
        }
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}

$methodLabels = ['gcash' => 'GCash', 'maya' => 'Maya'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Villaflores Gaming Cafe</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Science+Gothic:wght@400;500;600;700;800;900&family=Rubik:wght@400;500;600;700&display=swap');

  :root {
    --background: #08090c;
    --foreground: #f5f7fa;
    --card: #101319;
    --card-foreground: #f5f7fa;
    --muted: #171b22;
    --muted-foreground: #8b939f;
    --cerulean: #00a1f5;
    --pink: #f6047e;
    --border: rgba(255, 255, 255, 0.09);
    --font-display: 'Science Gothic', 'Arial Narrow', sans-serif;
    --font-body: 'Rubik', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    background-color: var(--background);
    background-image:
      linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
    background-size: 46px 46px;
    color: var(--foreground);
    font-family: var(--font-body);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    line-height: 1.5;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid var(--border);
  }

  .brand {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 22px;
    letter-spacing: -0.01em;
    text-transform: uppercase;
  }
  .brand span { color: var(--cerulean); }

  .subtitle {
    margin-top: 4px;
    color: var(--muted-foreground);
    font-size: 13px;
  }

  .header-right { display: flex; align-items: center; gap: 20px; }

  .btn-logout {
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--foreground);
    border: 1px solid var(--border);
    padding: 9px 16px;
    border-radius: 6px;
    text-decoration: none;
    transition: border-color 0.2s ease, color 0.2s ease;
  }
  .btn-logout:hover { border-color: var(--pink); color: var(--pink); }

  main { padding: 40px 32px 64px; max-width: 1280px; margin: 0 auto; }

  .flash {
    background: rgba(0,161,245,0.08);
    border: 1px solid var(--cerulean);
    color: var(--cerulean);
    padding: 14px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
  }

  .stat-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 36px;
  }

  .stat-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
  }

  .stat-num {
    font-family: var(--font-display);
    font-size: 36px;
    font-weight: 800;
    line-height: 1;
  }
  .stat-card:nth-child(1) .stat-num { color: var(--cerulean); }
  .stat-card:nth-child(2) .stat-num { color: var(--pink); }
  .stat-card:nth-child(3) .stat-num { color: var(--cerulean); }
  .stat-card:nth-child(4) .stat-num { color: var(--pink); }

  .stat-label {
    margin-top: 6px;
    color: var(--muted-foreground);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
  }

  section.panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 28px;
    overflow: hidden;
  }

  .panel-head {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .panel-head h2 {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 17px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .panel-head .count {
    color: var(--muted-foreground);
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
  }

  .table-scroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }

  thead th {
    text-align: left;
    padding: 12px 24px;
    color: var(--muted-foreground);
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
  }

  tbody td {
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    color: var(--foreground);
    white-space: nowrap;
  }

  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: var(--muted); }

  .cell-sub { color: var(--muted-foreground); font-size: 12px; margin-top: 2px; }

  .badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .badge.confirmed, .badge.paid { background: rgba(0,161,245,0.15); color: var(--cerulean); }
  .badge.pending, .badge.unpaid { background: var(--muted); color: var(--muted-foreground); border: 1px solid var(--border); }
  .badge.cancelled, .badge.failed { background: rgba(246,4,126,0.15); color: var(--pink); }
  .badge.default { background: var(--muted); color: var(--muted-foreground); }

  .btn-delete {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--pink);
    padding: 7px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease;
  }
  .btn-delete:hover { border-color: var(--pink); background: rgba(246,4,126,0.08); }

  .btn-confirm {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--cerulean);
    padding: 7px 14px;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease;
  }
  .btn-confirm:hover { border-color: var(--cerulean); background: rgba(0,161,245,0.08); }

  .btn-edit {
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--foreground);
    padding: 7px 14px;
    border-radius: 6px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: border-color 0.2s ease, color 0.2s ease;
  }
  .btn-edit:hover { border-color: var(--foreground); }

  .row-actions { display: flex; gap: 8px; flex-wrap: wrap; }

  .empty-row td {
    text-align: center;
    color: var(--muted-foreground);
    padding: 40px;
    white-space: normal;
  }

  .error-box {
    background: rgba(246,4,126,0.08);
    border: 1px solid var(--pink);
    color: #ffb3d6;
    padding: 16px 24px;
    border-radius: 12px;
    margin-bottom: 28px;
    font-size: 14px;
  }
  .error-box code { color: var(--pink); }
</style>
</head>
<body>

<header>
  <div>
    <div class="brand">VILLAFLORES <span>ADMIN</span></div>
    <div class="subtitle">Accounts &amp; booking overview</div>
  </div>
  <div class="header-right">
    <div class="subtitle">Hi, <?= h($_SESSION['admin_full_name'] ?? 'Admin') ?></div>
    <div class="subtitle">Updated <?= h(date("M j, Y — g:i A")) ?></div>
    <a href="admin_register.php" class="btn-logout">Add Admin</a>
    <a href="admin_logout.php" class="btn-logout">Log Out</a>
  </div>
</header>

<main>

  <?php if ($flash): ?>
    <div class="flash"><?= h($flash) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="error-box">
      Couldn't load data: <code><?= h($error) ?></code>
    </div>
  <?php endif; ?>

  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-num"><?= h($stats["total_users"]) ?></div>
      <div class="stat-label">Registered accounts</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= h($stats["total_bookings"]) ?></div>
      <div class="stat-label">Total bookings</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= h($stats["confirmed_bookings"]) ?></div>
      <div class="stat-label">Confirmed</div>
    </div>
    <div class="stat-card">
      <div class="stat-num"><?= h($stats["today_bookings"]) ?></div>
      <div class="stat-label">Booked for today</div>
    </div>
  </div>

  <section class="panel">
    <div class="panel-head">
      <h2>Bookings</h2>
      <span class="count"><?= h(count($bookings)) ?> total</span>
    </div>
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>User</th>
          <th>Plan</th>
          <th>Date</th>
          <th>Time</th>
          <th>Stations</th>
          <th>Amount</th>
          <th>Payment</th>
          <th>Status</th>
          <th>Booked on</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr class="empty-row"><td colspan="10">No bookings yet.</td></tr>
        <?php else: foreach ($bookings as $b):
          $status = $b["status"] ?? "";
          $payStatus = $b["payment_status"] ?? "";
          $pesos = number_format(($b["amount"] ?? 0) / 100, 2);
          $methodLabel = $methodLabels[$b["payment_method"]] ?? ($b["payment_method"] ?: "—");
        ?>
          <tr>
            <td>
              <?= h($b["full_name"] ?? "Deleted user") ?>
              <?php if (!empty($b["email"])): ?><div class="cell-sub"><?= h($b["email"]) ?></div><?php endif; ?>
            </td>
            <td><?= h($b["plan_name"]) ?></td>
            <td><?= h($b["booking_date"]) ?></td>
            <td><?= h(substr($b["booking_time"], 0, 5)) ?></td>
            <td><?= h($b["stations"]) ?></td>
            <td>₱<?= h($pesos) ?></td>
            <td>
              <?= h($methodLabel) ?>
              <div class="cell-sub"><span class="badge <?= h($payStatus ?: 'default') ?>"><?= h($payStatus ?: '—') ?></span></div>
            </td>
            <td><span class="badge <?= h($status ?: 'default') ?>"><?= h($status ?: '—') ?></span></td>
            <td><?= h($b["created_at"]) ?></td>
            <td>
              <div class="row-actions">
                <?php if ($status !== 'confirmed'): ?>
                  <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="update_booking_status">
                    <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                    <input type="hidden" name="new_status" value="confirmed">
                    <button type="submit" class="btn-confirm">Confirm</button>
                  </form>
                <?php endif; ?>
                <?php if ($status !== 'cancelled'): ?>
                  <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="update_booking_status">
                    <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                    <input type="hidden" name="new_status" value="cancelled">
                    <button type="submit" class="btn-delete">Decline</button>
                  </form>
                <?php endif; ?>
                <form method="POST" action="admin.php" onsubmit="return confirm('Delete this booking? This can\'t be undone.');">
                  <input type="hidden" name="action" value="delete_booking">
                  <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                  <button type="submit" class="btn-delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h2>Registered accounts</h2>
      <span class="count"><?= h(count($users)) ?> total</span>
    </div>
    <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
          <tr class="empty-row"><td colspan="4">No accounts yet.</td></tr>
        <?php else: foreach ($users as $u): ?>
          <tr>
            <td><?= h($u["full_name"]) ?></td>
            <td><?= h($u["email"]) ?></td>
            <td><?= h($u["created_at"]) ?></td>
            <td>
              <div class="row-actions">
                <a href="edit_user.php?id=<?= h($u["id"]) ?>" class="btn-edit">Edit</a>
                <form method="POST" action="admin.php" onsubmit="return confirm('Delete this account? Their bookings and reviews will be deleted too. This can\'t be undone.');">
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= h($u["id"]) ?>">
                  <button type="submit" class="btn-delete">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
    </div>
  </section>

</main>
</body>
</html>