<?php

session_start();
require '../database/config.php';

if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

$selfDeleteBlocked = !empty($_SESSION['self_delete_blocked']);
unset($_SESSION['self_delete_blocked']);

$initialTab = 'dashboard';
if (isset($_GET['new_booking'])) {
    $initialTab = 'bookings';
} elseif (isset($_GET['edit_user'])) {
    $initialTab = 'accounts';
}
$tabPageTitles = ['dashboard' => 'Dashboard', 'bookings' => 'Bookings', 'accounts' => 'Registered Accounts', 'stations' => 'PC Stations', 'reviews' => 'Customer Reviews'];

$error = null;
$users = [];
$bookings = [];
$reviews = [];
$bookingStationsById = [];
$createBookingError = null;
$updateUserError = null;
$stats = ["total_users" => 0, "total_bookings" => 0, "active_bookings" => 0, "today_bookings" => 0];
define("TOTAL_PC_STATIONS", 30);
$pcAssignments = array_fill(1, TOTAL_PC_STATIONS, null);

function h($val) {
    return htmlspecialchars($val ?? "", ENT_QUOTES, "UTF-8");
}

try {

    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_booking") {
        $bookingId = (int)($_POST["booking_id"] ?? 0);
        if ($bookingId > 0) {
            $del = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $del->execute([$bookingId]);
        }

        header("Location: admin.php");
        exit;
    }

   
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_booking_status") {
        $bookingId = (int)($_POST["booking_id"] ?? 0);
        $newStatus = $_POST["new_status"] ?? "";
        if ($bookingId > 0 && in_array($newStatus, ["confirmed", "cancelled", "pending"], true)) {
            $upd = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $upd->execute([$newStatus, $bookingId]);
        }
        header("Location: admin.php");
        exit;
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_booking_payment") {
        $bookingId = (int)($_POST["booking_id"] ?? 0);
        $newPaymentStatus = $_POST["new_payment_status"] ?? "";
        if ($bookingId > 0 && in_array($newPaymentStatus, ["paid", "unpaid", "failed"], true)) {
            $upd = $pdo->prepare("UPDATE bookings SET payment_status = ? WHERE id = ?");
            $upd->execute([$newPaymentStatus, $bookingId]);
        }
        header("Location: admin.php");
        exit;
    }

    $createBookingError = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "create_booking") {
        $cbUserId    = (int)($_POST["user_id"] ?? 0);
        $cbPlan      = $_POST["plan_name"] ?? "";
        $cbDate      = $_POST["booking_date"] ?? "";
        $cbTime      = $_POST["booking_time"] ?? "";
        $cbStation   = (int)($_POST["station_number"] ?? 0);
        $cbMethod    = $_POST["payment_method"] ?? "";
        $cbPayStatus = $_POST["payment_status"] ?? "unpaid";
        $cbStatus    = $_POST["status"] ?? "pending";

        $cbAllowedPlans   = ["Hourly Gaming" => 5000, "Extended Gaming" => 20000, "Gaming Package" => 18000];
        $cbAllowedMethods = ["gcash", "maya", ""];

        if ($cbUserId <= 0) {
            $createBookingError = "Please choose a customer.";
        } elseif (!array_key_exists($cbPlan, $cbAllowedPlans)) {
            $createBookingError = "Please choose a valid plan.";
        } elseif ($cbDate === "" || $cbTime === "") {
            $createBookingError = "Please set a date and time.";
        } elseif ($cbStation < 1 || $cbStation > TOTAL_PC_STATIONS) {
            $createBookingError = "Please choose a valid PC station (1-" . TOTAL_PC_STATIONS . ").";
        } elseif (!in_array($cbMethod, $cbAllowedMethods, true)) {
            $createBookingError = "Please choose a valid payment method.";
        } else {

            $conflict = $pdo->prepare(
                "SELECT 1 FROM booking_stations bs
                 JOIN bookings b ON b.id = bs.booking_id
                 WHERE bs.station_number = ? AND bs.booking_date = ? AND bs.booking_time = ?
                   AND b.status != 'cancelled'"
            );
            $conflict->execute([$cbStation, $cbDate, $cbTime]);

            if ($conflict->fetch()) {
                $createBookingError = "This station has been booked! Try something else.";
            } else {
                try {
                    $pdo->beginTransaction();

                    $insert = $pdo->prepare(
                        "INSERT INTO bookings (user_id, plan_name, booking_date, booking_time, stations, amount, payment_method, payment_status, status)
                         VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?)"
                    );
                    $insert->execute([
                        $cbUserId, $cbPlan, $cbDate, $cbTime,
                        $cbAllowedPlans[$cbPlan],
                        $cbMethod !== "" ? $cbMethod : null,
                        $cbPayStatus, $cbStatus,
                    ]);
                    $newBookingId = $pdo->lastInsertId();

                    $assign = $pdo->prepare(
                        "INSERT INTO booking_stations (booking_id, station_number, booking_date, booking_time) VALUES (?, ?, ?, ?)"
                    );
                    $assign->execute([$newBookingId, $cbStation, $cbDate, $cbTime]);

                    $pdo->commit();
                    header("Location: admin.php");
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $createBookingError = "This station has been booked! Try something else.";
                }
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_review") {
        $reviewId = (int)($_POST["review_id"] ?? 0);
        if ($reviewId > 0) {
            $del = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $del->execute([$reviewId]);
        }
        header("Location: admin.php");
        exit;
    }

    $updateUserError = null;
    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "update_user") {
        $uuId       = (int)($_POST["user_id"] ?? 0);
        $uuName     = trim($_POST["full_name"] ?? "");
        $uuEmail    = trim($_POST["email"] ?? "");
        $uuRole     = $_POST["role"] ?? "customer";
        $uuPassword = $_POST["new_password"] ?? "";

        if ($uuId <= 0 || $uuName === "" || $uuEmail === "" || !filter_var($uuEmail, FILTER_VALIDATE_EMAIL)) {
            $updateUserError = "Please provide a valid name and email.";
        } elseif (!in_array($uuRole, ["customer", "admin"], true)) {
            $updateUserError = "Invalid role.";
        } else {
            $dupe = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $dupe->execute([$uuEmail, $uuId]);
            if ($dupe->fetch()) {
                $updateUserError = "Another account already uses that email.";
            } else {
                if ($uuPassword !== "") {
                    if (strlen($uuPassword) < 8) {
                        $updateUserError = "New password must be at least 8 characters.";
                    } else {
                        $hash = password_hash($uuPassword, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, password_hash = ? WHERE id = ?");
                        $upd->execute([$uuName, $uuEmail, $uuRole, $hash, $uuId]);
                    }
                } else {
                    $upd = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?");
                    $upd->execute([$uuName, $uuEmail, $uuRole, $uuId]);
                }

                if (!$updateUserError) {
                    header("Location: admin.php");
                    exit;
                }
            }
        }
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "delete_user") {
        $duId = (int)($_POST["user_id"] ?? 0);
        if ($duId > 0 && $duId === (int) $_SESSION["user_id"]) {
            $_SESSION["self_delete_blocked"] = true;
        } elseif ($duId > 0) {
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$duId]);
        }
        header("Location: admin.php");
        exit;
    }

    // --- users 
    $users = $pdo->query("SELECT id, full_name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
    $stats["total_users"] = count($users);

    // --- reviews 
    $reviews = $pdo->query(
        "SELECT r.id, r.rating, r.comment, r.created_at, u.full_name
         FROM reviews r
         LEFT JOIN users u ON u.id = r.user_id
         ORDER BY r.created_at DESC"
    )->fetchAll();

    // --- bookings 
    $bookings = $pdo->query(
        "SELECT b.id, b.user_id, u.full_name, b.plan_name, b.stations,
                b.booking_date, b.booking_time, b.status,
                b.payment_method, b.payment_status, b.created_at
         FROM bookings b
         LEFT JOIN users u ON u.id = b.user_id
         ORDER BY b.booking_date DESC, b.booking_time DESC"
    )->fetchAll();

    $bookingStationsById = [];
    if (!empty($bookings)) {
        $bIds = array_column($bookings, "id");
        $placeholders = implode(",", array_fill(0, count($bIds), "?"));
        $stStmt = $pdo->prepare("SELECT booking_id, station_number FROM booking_stations WHERE booking_id IN ($placeholders) ORDER BY station_number");
        $stStmt->execute($bIds);
        foreach ($stStmt->fetchAll() as $row) {
            $bookingStationsById[$row["booking_id"]][] = $row["station_number"];
        }
    }

    $stats["total_bookings"] = count($bookings);
    foreach ($bookings as $b) {
        if (strtolower($b["status"] ?? "") === "active" || strtolower($b["status"] ?? "") === "confirmed") {
            $stats["active_bookings"]++;
        }
        if (($b["booking_date"] ?? "") === date("Y-m-d")) {
            $stats["today_bookings"]++;
        }
    }

    // --- PC station view 
    $pcAssignments = array_fill(1, TOTAL_PC_STATIONS, null);

    $todaysAssignments = $pdo->query(
        "SELECT bs.station_number, u.full_name, b.plan_name, b.booking_time, b.status
         FROM booking_stations bs
         JOIN bookings b ON b.id = bs.booking_id
         LEFT JOIN users u ON u.id = b.user_id
         WHERE bs.booking_date = CURDATE() AND b.status != 'cancelled'"
    )->fetchAll();

    foreach ($todaysAssignments as $a) {
        $stationNumber = (int) $a["station_number"];
        if ($stationNumber >= 1 && $stationNumber <= TOTAL_PC_STATIONS) {
            $pcAssignments[$stationNumber] = $a;
        }
    }
} catch (PDOException $e) {
    $error = $e->getMessage();
}
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

  html, body { height: 100%; }

  body {
    background-color: var(--background);
    color: var(--foreground);
    font-family: var(--font-body);
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    line-height: 1.5;
    display: flex;
    min-height: 100vh;
  }

  .sidebar {
    width: 260px;
    flex-shrink: 0;
    background-color: var(--card);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    padding: 28px 20px;
    position: sticky;
    top: 0;
    height: 100vh;
  }

  .sidebar-brand {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 18px;
    letter-spacing: -0.01em;
    text-transform: uppercase;
    padding: 0 8px;
  }
  .sidebar-brand span { color: var(--cerulean); }

  .sidebar-welcome {
    margin-top: 6px;
    padding: 0 8px;
    font-size: 12px;
    color: var(--muted-foreground);
  }

  .sidebar-nav {
    margin-top: 32px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-grow: 1;
  }

  .sidebar-nav button {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    text-align: left;
    background: none;
    border: none;
    border-radius: 8px;
    padding: 12px 12px;
    font-family: var(--font-body);
    font-size: 14px;
    font-weight: 500;
    color: var(--muted-foreground);
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
  }

  .sidebar-nav button .icon {
    width: 18px;
    text-align: center;
    font-size: 15px;
  }

  .sidebar-nav button:hover {
    background-color: var(--muted);
    color: var(--foreground);
  }

  .sidebar-nav button.active {
    background-color: var(--cerulean);
    color: #000000;
    font-weight: 700;
  }

  .sidebar-footer {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .sidebar-footer a {
    display: block;
    text-align: center;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 0;
    font-size: 13px;
    color: var(--muted-foreground);
    text-decoration: none;
    transition: border-color 0.2s ease, color 0.2s ease;
  }

  .sidebar-footer a:hover {
    border-color: var(--pink);
    color: var(--pink);
  }

  .content-area {
    flex-grow: 1;
    background-image:
      linear-gradient(rgba(255, 255, 255, 0.025) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
    background-size: 46px 46px;
    min-height: 100vh;
  }

  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 32px;
    border-bottom: 1px solid var(--border);
  }

  .page-title {
    font-family: var(--font-display);
    font-weight: 800;
    font-size: 22px;
    text-transform: uppercase;
    letter-spacing: -0.01em;
  }

  .subtitle {
    margin-top: 4px;
    color: var(--muted-foreground);
    font-size: 13px;
  }

  main { padding: 40px 32px 64px; max-width: 1280px; margin: 0 auto; }

  .tab-panel { display: none; }
  .tab-panel.active { display: block; }

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
  }

  tbody td {
    padding: 14px 24px;
    border-bottom: 1px solid var(--border);
    color: var(--foreground);
  }

  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover { background: var(--muted); }

  .badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
  }
  .badge.confirmed, .badge.active, .badge.paid { background: rgba(0,161,245,0.15); color: var(--cerulean); }
  .badge.pending, .badge.unpaid { background: var(--muted); color: var(--muted-foreground); border: 1px solid var(--border); }
  .badge.cancelled, .badge.failed { background: rgba(246,4,126,0.15); color: var(--pink); }
  .badge.default { background: var(--muted); color: var(--muted-foreground); }

  .empty-row td {
    text-align: center;
    color: var(--muted-foreground);
    padding: 40px;
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

  .delete-form { display: inline; }
  .delete-btn {
    background: none;
    border: 1px solid var(--pink);
    color: var(--pink);
    border-radius: 6px;
    padding: 6px 12px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  .delete-btn:hover {
    background-color: var(--pink);
    color: #000000;
  }

  .row-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
  .row-actions form { display: inline; }

  .accept-btn, .edit-btn {
    background: none;
    border: 1px solid var(--cerulean);
    color: var(--cerulean);
    border-radius: 6px;
    padding: 6px 12px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  .accept-btn:hover, .edit-btn:hover {
    background-color: var(--cerulean);
    color: #000000;
  }

  .reject-btn {
    background: none;
    border: 1px solid var(--muted-foreground);
    color: var(--muted-foreground);
    border-radius: 6px;
    padding: 6px 12px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease;
  }
  .reject-btn:hover {
    background-color: var(--muted-foreground);
    color: #000000;
  }

  .add-new-btn {
    display: inline-block;
    border: 1px dashed var(--border);
    border-radius: 8px;
    padding: 12px 20px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--cerulean);
    text-decoration: none;
    transition: border-color 0.2s ease;
  }
  .add-new-btn:hover {
    border-color: var(--cerulean);
  }

  .admin-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 16px;
  }
  .admin-form-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .admin-form-field label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--muted-foreground);
  }
  .admin-form-field input,
  .admin-form-field select {
    background-color: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 12px;
    color: var(--foreground);
    font-family: var(--font-body);
    font-size: 14px;
  }
  .admin-form-field input:focus,
  .admin-form-field select:focus {
    outline: none;
    border-color: var(--cerulean);
  }
  .admin-form-submit {
    border: none;
    border-radius: 6px;
    padding: 12px 24px;
    font-family: var(--font-display);
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    background-color: var(--pink);
    color: #000000;
    cursor: pointer;
  }

  .pc-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
  }

  .pc-card {
    border-radius: 12px;
    border: 1px solid var(--border);
    background-color: var(--card);
    padding: 18px;
    min-height: 110px;
  }

  .pc-card.occupied {
    background-color: var(--cerulean);
    border-color: var(--cerulean);
  }

  .pc-card-name {
    font-family: var(--font-display);
    font-weight: 700;
    font-size: 15px;
    text-transform: uppercase;
  }

  .pc-card.occupied .pc-card-name,
  .pc-card.occupied .pc-card-detail {
    color: #000000;
  }

  .pc-card-status {
    margin-top: 10px;
    display: inline-block;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    background: var(--muted);
    color: var(--muted-foreground);
  }

  .pc-card.occupied .pc-card-status {
    background: rgba(0, 0, 0, 0.2);
    color: #000000;
  }

  .pc-card-detail {
    margin-top: 8px;
    font-size: 12px;
    color: var(--muted-foreground);
    line-height: 1.5;
  }

  .pc-legend {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 20px;
    font-size: 13px;
    color: var(--muted-foreground);
  }

  .pc-legend span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .pc-legend .swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    display: inline-block;
  }

  .pc-legend .swatch.idle { background: var(--muted); border: 1px solid var(--border); }
  .pc-legend .swatch.occupied { background: var(--cerulean); }

  @media (max-width: 1100px) {
    .pc-grid { grid-template-columns: repeat(3, 1fr); }
  }

  @media (max-width: 900px) {
    body { flex-direction: column; }
    .sidebar { width: 100%; height: auto; position: relative; flex-direction: row; align-items: center; flex-wrap: wrap; }
    .sidebar-nav { flex-direction: row; margin-top: 0; flex-wrap: wrap; }
    .sidebar-footer { flex-direction: row; margin-left: auto; }
    .pc-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>

</head>
<body>

<aside class="sidebar">
  <div>
    <div class="sidebar-brand">VILLAFLORES <span>GAMING CAFE</span></div>
    <div class="sidebar-welcome">Welcome, <?= h($_SESSION['full_name']) ?></div>
  </div>

  <nav class="sidebar-nav">
    <button type="button" class="<?= $initialTab === 'dashboard' ? 'active' : '' ?>" data-tab="dashboard" onclick="showTab('dashboard', this)">
      <span class="icon">▦</span> Dashboard
    </button>
    <button type="button" class="<?= $initialTab === 'bookings' ? 'active' : '' ?>" data-tab="bookings" onclick="showTab('bookings', this)">
      <span class="icon">▤</span> Bookings
    </button>
    <button type="button" class="<?= $initialTab === 'accounts' ? 'active' : '' ?>" data-tab="accounts" onclick="showTab('accounts', this)">
      <span class="icon">◈</span> Accounts
    </button>
    <button type="button" class="<?= $initialTab === 'stations' ? 'active' : '' ?>" data-tab="stations" onclick="showTab('stations', this)">
      <span class="icon">▥</span> PC Stations
    </button>
    <button type="button" class="<?= $initialTab === 'reviews' ? 'active' : '' ?>" data-tab="reviews" onclick="showTab('reviews', this)">
      <span class="icon">★</span> Reviews
    </button>
  </nav>

  <div class="sidebar-footer">
    <a href="../homepage/index.php">← Back to Site</a>
    <a href="../logout/logout.php">Sign Out</a>
  </div>
</aside>

<div class="content-area">
  <header>
    <div>
      <div class="page-title" id="pageTitle"><?= h($tabPageTitles[$initialTab]) ?></div>
      <div class="subtitle">Accounts &amp; booking overview</div>
    </div>
    <div class="subtitle">Updated <?= h(date("M j, Y — g:i A")) ?></div>
  </header>

  <main>

    <?php if ($error): ?>
      <div class="error-box">
        Couldn't connect to the database: <code><?= h($error) ?></code><br>
        Check the database settings in <code>database/config.php</code>.
      </div>
    <?php endif; ?>

    <div class="tab-panel <?= $initialTab === 'dashboard' ? 'active' : '' ?>" id="tab-dashboard">
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
          <div class="stat-num"><?= h($stats["active_bookings"]) ?></div>
          <div class="stat-label">Active / confirmed</div>
        </div>
        <div class="stat-card">
          <div class="stat-num"><?= h($stats["today_bookings"]) ?></div>
          <div class="stat-label">Booked for today</div>
        </div>
      </div>

      <section class="panel">
        <div class="panel-head">
          <h2>Recent Bookings</h2>
          <span class="count"><?= h(count($bookings)) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Plan</th>
              <th>Date</th>
              <th>Status</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bookings)): ?>
              <tr class="empty-row"><td colspan="5">No bookings yet.</td></tr>
            <?php else: foreach (array_slice($bookings, 0, 5) as $b):
              $status = strtolower($b["status"] ?? "");
              $badgeClass = in_array($status, ["confirmed", "active", "pending", "cancelled"]) ? $status : "default";
              $paymentStatus = strtolower($b["payment_status"] ?? "");
              $paymentBadgeClass = in_array($paymentStatus, ["paid", "unpaid", "failed"]) ? $paymentStatus : "default";
            ?>
              <tr>
                <td><?= h($b["full_name"] ?? "Deleted user") ?></td>
                <td><?= h($b["plan_name"]) ?></td>
                <td><?= h($b["booking_date"]) ?></td>
                <td><span class="badge <?= h($badgeClass) ?>"><?= h($b["status"] ?? "—") ?></span></td>
                <td><span class="badge <?= h($paymentBadgeClass) ?>"><?= h($b["payment_status"] ?? "—") ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <div class="tab-panel <?= $initialTab === 'bookings' ? 'active' : '' ?>" id="tab-bookings">
      <section class="panel" style="margin-bottom: 20px; padding: 24px;">
        <?php if ($_GET['new_booking'] ?? false): ?>
          <div class="panel-head" style="padding: 0; border: none; margin-bottom: 16px;">
            <h2>Add Booking</h2>
            <a href="admin.php#bookings" onclick="showTab('bookings', document.querySelector('[data-tab=bookings]'))" style="font-size: 13px; color: var(--muted-foreground); text-decoration: none;">Cancel</a>
          </div>

          <?php if ($createBookingError): ?>
            <div class="error-box" style="margin-bottom: 16px;"><?= h($createBookingError) ?></div>
          <?php endif; ?>

          <form method="POST" action="admin.php" class="admin-form">
            <input type="hidden" name="action" value="create_booking">
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>Customer</label>
                <select name="user_id" required>
                  <option value="" disabled selected>Select a customer</option>
                  <?php foreach ($users as $u): ?>
                    <option value="<?= h($u["id"]) ?>"><?= h($u["full_name"]) ?> (<?= h($u["email"]) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="admin-form-field">
                <label>Plan</label>
                <select name="plan_name" required>
                  <option value="" disabled selected>Select a plan</option>
                  <option value="Hourly Gaming">Hourly Gaming (₱50)</option>
                  <option value="Extended Gaming">Extended Gaming (₱200)</option>
                  <option value="Gaming Package">Gaming Package (₱180)</option>
                </select>
              </div>
            </div>
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>Date</label>
                <input type="date" name="booking_date" required>
              </div>
              <div class="admin-form-field">
                <label>Time</label>
                <input type="time" name="booking_time" required>
              </div>
            </div>
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>PC Station (1-<?= h(TOTAL_PC_STATIONS) ?>)</label>
                <input type="number" name="station_number" min="1" max="<?= h(TOTAL_PC_STATIONS) ?>" required>
              </div>
              <div class="admin-form-field">
                <label>Payment Method</label>
                <select name="payment_method">
                  <option value="">— None —</option>
                  <option value="gcash">GCash</option>
                  <option value="maya">Maya</option>
                </select>
              </div>
            </div>
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>Payment Status</label>
                <select name="payment_status">
                  <option value="unpaid">Unpaid</option>
                  <option value="paid">Paid</option>
                  <option value="failed">Failed</option>
                </select>
              </div>
              <div class="admin-form-field">
                <label>Booking Status</label>
                <select name="status">
                  <option value="pending">Pending</option>
                  <option value="confirmed">Confirmed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
            </div>
            <button type="submit" class="admin-form-submit">Save Booking</button>
          </form>
        <?php else: ?>
          <a href="admin.php?new_booking=1#bookings" onclick="showTab('bookings', document.querySelector('[data-tab=bookings]'))" class="add-new-btn">+ Add Booking</a>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head">
          <h2>All Bookings</h2>
          <span class="count"><?= h(count($bookings)) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Plan</th>
              <th>Station(s)</th>
              <th>Date</th>
              <th>Time</th>
              <th>Status</th>
              <th>Payment</th>
              <th>Booked on</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bookings)): ?>
              <tr class="empty-row"><td colspan="9">No bookings yet.</td></tr>
            <?php else: foreach ($bookings as $b):
              $status = strtolower($b["status"] ?? "");
              $badgeClass = in_array($status, ["confirmed", "active", "pending", "cancelled"]) ? $status : "default";

              $paymentStatus = strtolower($b["payment_status"] ?? "");
              $paymentBadgeClass = in_array($paymentStatus, ["paid", "unpaid", "failed"]) ? $paymentStatus : "default";
              $paymentMethodLabel = $b["payment_method"] === "gcash" ? "GCash" : ($b["payment_method"] === "maya" ? "Maya" : "—");

              $assignedStations = $bookingStationsById[$b["id"]] ?? [];
              $stationsDisplay = $assignedStations ? "PC " . implode(", PC ", $assignedStations) : $b["stations"] . " station(s)";
            ?>
              <tr>
                <td><?= h($b["full_name"] ?? "Deleted user") ?></td>
                <td><?= h($b["plan_name"]) ?></td>
                <td><?= h($stationsDisplay) ?></td>
                <td><?= h($b["booking_date"]) ?></td>
                <td><?= h($b["booking_time"]) ?></td>
                <td><span class="badge <?= h($badgeClass) ?>"><?= h($b["status"] ?? "—") ?></span></td>
                <td>
                  <?= h($paymentMethodLabel) ?> —
                  <span class="badge <?= h($paymentBadgeClass) ?>"><?= h($b["payment_status"] ?? "—") ?></span>
                </td>
                <td><?= h($b["created_at"]) ?></td>
                <td class="row-actions">
                  <?php if ($status === "pending"): ?>
                    <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Accept this booking?');">
                      <input type="hidden" name="action" value="update_booking_status">
                      <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                      <input type="hidden" name="new_status" value="confirmed">
                      <button type="submit" class="accept-btn">Accept</button>
                    </form>
                    <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Reject this booking?');">
                      <input type="hidden" name="action" value="update_booking_status">
                      <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                      <input type="hidden" name="new_status" value="cancelled">
                      <button type="submit" class="reject-btn">Reject</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($paymentStatus !== "paid" && $b["payment_method"]): ?>
                    <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Mark this booking as paid?');">
                      <input type="hidden" name="action" value="update_booking_payment">
                      <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                      <input type="hidden" name="new_payment_status" value="paid">
                      <button type="submit" class="accept-btn">Mark Paid</button>
                    </form>
                  <?php endif; ?>
                  <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_booking">
                    <input type="hidden" name="booking_id" value="<?= h($b["id"]) ?>">
                    <button type="submit" class="delete-btn">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <div class="tab-panel <?= $initialTab === 'accounts' ? 'active' : '' ?>" id="tab-accounts">
      <?php if ($selfDeleteBlocked): ?>
        <div class="error-box">You can't delete the admin account you're currently logged in as.</div>
      <?php endif; ?>

      <?php
        $editingUser = null;
        $editUserId = (int)($_GET['edit_user'] ?? 0);
        if ($editUserId > 0) {
            foreach ($users as $u) {
                if ((int) $u['id'] === $editUserId) {
                    $editingUser = $u;
                    break;
                }
            }
        }
      ?>
      <?php if ($editingUser): ?>
        <section class="panel" style="margin-bottom: 20px; padding: 24px;">
          <div class="panel-head" style="padding: 0; border: none; margin-bottom: 16px;">
            <h2>Edit Account</h2>
            <a href="admin.php#accounts" onclick="showTab('accounts', document.querySelector('[data-tab=accounts]'))" style="font-size: 13px; color: var(--muted-foreground); text-decoration: none;">Cancel</a>
          </div>

          <?php if ($updateUserError): ?>
            <div class="error-box" style="margin-bottom: 16px;"><?= h($updateUserError) ?></div>
          <?php endif; ?>

          <form method="POST" action="admin.php" class="admin-form">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= h($editingUser['id']) ?>">
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>Full Name</label>
                <input type="text" name="full_name" value="<?= h($editingUser['full_name']) ?>" required>
              </div>
              <div class="admin-form-field">
                <label>Email</label>
                <input type="email" name="email" value="<?= h($editingUser['email']) ?>" required>
              </div>
            </div>
            <div class="admin-form-row">
              <div class="admin-form-field">
                <label>Role</label>
                <select name="role">
                  <option value="customer" <?= $editingUser['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                  <option value="admin" <?= $editingUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
              </div>
              <div class="admin-form-field">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" name="new_password" minlength="8">
              </div>
            </div>
            <button type="submit" class="admin-form-submit">Save Changes</button>
          </form>
        </section>
      <?php endif; ?>

      <section class="panel">
        <div class="panel-head">
          <h2>Registered Accounts</h2>
          <span class="count"><?= h(count($users)) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Full Name</th>
              <th>Email</th>
              <th>Role</th>
              <th>Joined</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr class="empty-row"><td colspan="5">No accounts yet.</td></tr>
            <?php else: foreach ($users as $u): ?>
              <tr>
                <td><?= h($u["full_name"]) ?></td>
                <td><?= h($u["email"]) ?></td>
                <td><span class="badge <?= $u["role"] === "admin" ? "confirmed" : "default" ?>"><?= h($u["role"]) ?></span></td>
                <td><?= h($u["created_at"]) ?></td>
                <td class="row-actions">
                  <a href="admin.php?edit_user=<?= h($u["id"]) ?>#accounts" onclick="showTab('accounts', document.querySelector('[data-tab=accounts]'))" class="edit-btn">Edit</a>
                  <?php if ((int) $u["id"] !== (int) $_SESSION["user_id"]): ?>
                    <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Delete this account? Their bookings and reviews will be deleted too. This cannot be undone.');">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= h($u["id"]) ?>">
                      <button type="submit" class="delete-btn">Delete</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>
    </div>

    <div class="tab-panel <?= $initialTab === 'stations' ? 'active' : '' ?>" id="tab-stations">
      <section class="panel" style="padding: 24px;">
        <div class="pc-legend">
          <span><span class="swatch idle"></span> Available</span>
          <span><span class="swatch occupied"></span> Reserved today</span>
        </div>
        <p style="font-size: 12px; color: var(--muted-foreground); margin-bottom: 20px;">
          Shows today's actual PC assignments — customers now pick their specific station number when booking.
        </p>
        <div class="pc-grid">
          <?php for ($n = 1; $n <= TOTAL_PC_STATIONS; $n++):
            $assigned = $pcAssignments[$n];
          ?>
            <div class="pc-card <?= $assigned ? 'occupied' : '' ?>">
              <div class="pc-card-name">PC <?= h($n) ?></div>
              <?php if ($assigned): ?>
                <span class="pc-card-status">Reserved</span>
                <div class="pc-card-detail">
                  <?= h($assigned["full_name"] ?? "Deleted user") ?><br>
                  <?= h($assigned["plan_name"]) ?> — <?= h($assigned["booking_time"]) ?>
                </div>
              <?php else: ?>
                <span class="pc-card-status">Idle</span>
              <?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </section>
    </div>

    <div class="tab-panel <?= $initialTab === 'reviews' ? 'active' : '' ?>" id="tab-reviews">
      <section class="panel">
        <div class="panel-head">
          <h2>Customer Reviews</h2>
          <span class="count"><?= h(count($reviews)) ?> total</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>User</th>
              <th>Rating</th>
              <th>Feedback</th>
              <th>Posted on</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reviews)): ?>
              <tr class="empty-row"><td colspan="5">No reviews yet.</td></tr>
            <?php else: foreach ($reviews as $r): ?>
              <tr>
                <td><?= h($r["full_name"] ?? "Deleted user") ?></td>
                <td style="color: var(--pink); letter-spacing: 2px;">
                  <?= str_repeat("★", (int)$r["rating"]) . str_repeat("☆", 5 - (int)$r["rating"]) ?>
                </td>
                <td><?= $r["comment"] ? nl2br(h($r["comment"])) : '<span style="color: var(--muted-foreground);">—</span>' ?></td>
                <td><?= h($r["created_at"]) ?></td>
                <td>
                  <form class="delete-form" method="POST" action="admin.php" onsubmit="return confirm('Delete this review? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete_review">
                    <input type="hidden" name="review_id" value="<?= h($r["id"]) ?>">
                    <button type="submit" class="delete-btn">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </section>
    </div>

  </main>
</div>

<script>
  const pageTitles = { dashboard: 'Dashboard', bookings: 'Bookings', accounts: 'Registered Accounts', stations: 'PC Stations', reviews: 'Customer Reviews' };

  function showTab(tabName, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-' + tabName).classList.add('active');

    document.querySelectorAll('.sidebar-nav button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    document.getElementById('pageTitle').textContent = pageTitles[tabName];
  }
</script>

</body>
</html>