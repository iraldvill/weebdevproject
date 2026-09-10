<?php
session_start();
require '../database/config.php';

if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

if (($_SESSION['role'] ?? '') === 'admin') {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Not Available — Villaflores Gaming Cafe</title>
        <link rel="stylesheet" href="../style.css">
        <style>
            .error-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px; text-align: center; }
            .error-card { max-width: 440px; border-radius: 16px; border: 1px solid var(--pink); background-color: var(--card); padding: 40px; }
            .error-title { font-family: var(--font-display); font-size: 24px; font-weight: 800; text-transform: uppercase; color: var(--pink); }
            .error-sub { margin-top: 12px; font-size: 14px; color: var(--muted-foreground); line-height: 1.6; }
            .error-link { display: inline-block; margin-top: 24px; border: none; border-radius: 6px; padding: 12px 24px; font-family: var(--font-display); font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; background-color: var(--cerulean); color: #000000; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class="error-wrap brand-grid">
            <div class="error-card">
                <div class="error-title">Not Available for Admins</div>
                <p class="error-sub">Admin accounts can't book a seat. If you want to make a booking on a customer's behalf, use "Add Booking" from the admin dashboard instead.</p>
                <a href="../admin/admin.php" class="error-link">Back to Admin Panel</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

define('TOTAL_STATIONS', 30);

$allowedPlans   = ['Hourly Gaming', 'Extended Gaming', 'Gaming Package'];
$allowedMethods = ['gcash', 'maya'];

$planPrices = [
    'Hourly Gaming'   => 50,
    'Extended Gaming' => 200,
    'Gaming Package'  => 180,
];

$selectedPlan = $_GET['plan'] ?? '';
if (!in_array($selectedPlan, $allowedPlans, true)) {
    $selectedPlan = '';
}

$errors  = [];
$success = false;
$successMethod = '';
$successStations = [];

$old = [
    'plan'           => $selectedPlan,
    'date'           => '',
    'time'           => '',
    'payment_method' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan          = $_POST['plan'] ?? '';
    $date          = $_POST['booking_date'] ?? '';
    $time          = $_POST['booking_time'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $stationsRaw   = $_POST['selected_stations'] ?? '';

    $old = [
        'plan'           => $plan,
        'date'           => $date,
        'time'           => $time,
        'payment_method' => $paymentMethod,
    ];

    $selectedStations = array_filter(array_map('intval', explode(',', $stationsRaw)));
    $selectedStations = array_values(array_unique($selectedStations));

    if (!in_array($plan, $allowedPlans, true)) {
        $errors[] = 'Please choose a valid plan.';
    }

    $dateObj = DateTime::createFromFormat('Y-m-d', $date);
    $today   = new DateTime('today');
    if (!$dateObj || $dateObj < $today) {
        $errors[] = 'Please choose a valid date that is today or later.';
    }

    if (!DateTime::createFromFormat('H:i', $time)) {
        $errors[] = 'Please choose a valid time.';
    }

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please choose where you will pay.';
    }

    if (empty($selectedStations)) {
        $errors[] = 'Please select at least one PC station.';
    }
    foreach ($selectedStations as $s) {
        if ($s < 1 || $s > TOTAL_STATIONS) {
            $errors[] = 'One of the selected stations is invalid.';
            break;
        }
    }

    if (empty($errors)) {
        $placeholders = implode(',', array_fill(0, count($selectedStations), '?'));
        $conflictCheck = $pdo->prepare(
            "SELECT bs.station_number
             FROM booking_stations bs
             JOIN bookings b ON b.id = bs.booking_id
             WHERE bs.booking_date = ? AND bs.booking_time = ?
               AND b.status != 'cancelled'
               AND bs.station_number IN ($placeholders)"
        );
        $conflictCheck->execute(array_merge([$date, $time], $selectedStations));
        $conflicts = $conflictCheck->fetchAll();

        if (!empty($conflicts)) {
            $errors[] = 'This station has been booked! Try something else.';
        }
    }

    if (empty($errors)) {
        $amountCentavos = $planPrices[$plan] * 100;
        $stationsCount  = count($selectedStations);

        try {
            $pdo->beginTransaction();

            $insert = $pdo->prepare(
                'INSERT INTO bookings (user_id, plan_name, booking_date, booking_time, stations, amount, payment_method)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([$_SESSION['user_id'], $plan, $date, $time, $stationsCount, $amountCentavos, $paymentMethod]);
            $bookingId = $pdo->lastInsertId();

            $assignStation = $pdo->prepare(
                'INSERT INTO booking_stations (booking_id, station_number, booking_date, booking_time)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($selectedStations as $stationNumber) {
                $assignStation->execute([$bookingId, $stationNumber, $date, $time]);
            }

            $pdo->commit();

            $success          = true;
            $successMethod    = $paymentMethod;
            $successStations  = $selectedStations;
            $old = ['plan' => '', 'date' => '', 'time' => '', 'payment_method' => ''];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'This station has been booked! Try something else.';
        }
    }
}

$methodLabels = ['gcash' => 'GCash', 'maya' => 'Maya'];

$reservedRows = $pdo->query(
    "SELECT bs.station_number, bs.booking_date, bs.booking_time
     FROM booking_stations bs
     JOIN bookings b ON b.id = bs.booking_id
     WHERE b.status != 'cancelled'"
)->fetchAll();

$reservedSlots = array_map(function ($r) {
    return [
        'station' => (int) $r['station_number'],
        'date'    => $r['booking_date'],
        'time'    => substr($r['booking_time'], 0, 5), 
    ];
}, $reservedRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Seat — Villaflores Gaming Cafe</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .auth-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }
        .auth-card {
            width: 100%;
            max-width: 560px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 40px;
        }
        .auth-title {
            font-family: var(--font-display);
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .auth-sub {
            margin-top: 8px;
            font-size: 14px;
            color: var(--muted-foreground);
        }
        .auth-field {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .auth-field label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted-foreground);
        }
        .auth-field input,
        .auth-field select {
            background-color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px 14px;
            color: var(--foreground);
            font-family: var(--font-body);
            font-size: 14px;
        }
        .auth-field input:focus,
        .auth-field select:focus {
            outline: none;
            border-color: var(--cerulean);
        }
        .auth-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .auth-submit {
            margin-top: 28px;
            width: 100%;
            border: none;
            border-radius: 6px;
            padding: 14px 0;
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            background-color: var(--pink);
            color: #000000;
            cursor: pointer;
        }
        .auth-submit:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .auth-footer {
            margin-top: 20px;
            font-size: 14px;
            color: var(--muted-foreground);
            text-align: center;
        }
        .auth-footer a {
            color: var(--cerulean);
            text-decoration: none;
        }
        .auth-errors {
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid var(--pink);
            background-color: rgba(246, 4, 126, 0.08);
            padding: 14px 16px;
        }
        .auth-errors p {
            font-size: 13px;
            color: var(--pink);
            margin: 4px 0;
        }
        .auth-success {
            margin-top: 20px;
            border-radius: 8px;
            border: 1px solid var(--cerulean);
            background-color: rgba(0, 161, 245, 0.08);
            padding: 14px 16px;
        }
        .auth-success p {
            font-size: 13px;
            color: var(--cerulean);
            margin: 4px 0;
        }
        .payment-picked-row {
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--muted);
            padding: 12px 16px;
        }
        .payment-picked-row span {
            font-size: 14px;
            color: var(--foreground);
        }
        .payment-picked-row button {
            background: none;
            border: none;
            color: var(--cerulean);
            font-size: 13px;
            cursor: pointer;
            text-decoration: underline;
        }

        /* ---- Station picker ---- */
        .station-picker {
            margin-top: 8px;
            max-height: 260px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            background-color: var(--muted);
        }
        .station-btn {
            border-radius: 6px;
            border: 1px solid var(--border);
            background-color: var(--card);
            color: var(--foreground);
            font-family: var(--font-display);
            font-size: 12px;
            font-weight: 700;
            padding: 10px 0;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .station-btn:hover:not(:disabled) {
            border-color: var(--cerulean);
        }
        .station-btn.selected {
            background-color: var(--cerulean);
            border-color: var(--cerulean);
            color: #000000;
        }
        .station-btn:disabled {
            background-color: var(--background);
            color: var(--muted-foreground);
            cursor: not-allowed;
            opacity: 0.5;
        }
        .station-legend {
            margin-top: 10px;
            display: flex;
            gap: 16px;
            font-size: 12px;
            color: var(--muted-foreground);
        }
        .station-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .station-legend .swatch {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            display: inline-block;
        }
        .station-legend .swatch.available { background: var(--card); border: 1px solid var(--border); }
        .station-legend .swatch.selected { background: var(--cerulean); }
        .station-legend .swatch.taken { background: var(--background); opacity: 0.5; }
        .station-hint {
            margin-top: 6px;
            font-size: 12px;
            color: var(--muted-foreground);
        }

        /* ---- Payment method pop-up ---- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 24px;
        }
        .modal-box {
            width: 100%;
            max-width: 380px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background-color: var(--card);
            padding: 32px;
            text-align: center;
        }
        .modal-box h2 {
            font-family: var(--font-display);
            font-size: 20px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .modal-box p {
            margin-top: 8px;
            font-size: 13px;
            color: var(--muted-foreground);
        }
        .payment-modal-options {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .payment-modal-options button {
            border-radius: 8px;
            border: 1px solid var(--border);
            background-color: var(--muted);
            color: var(--foreground);
            font-family: var(--font-display);
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 14px 0;
            cursor: pointer;
            transition: border-color 0.2s ease, color 0.2s ease;
        }
        .payment-modal-options button.gcash-option:hover {
            border-color: var(--cerulean);
            color: var(--cerulean);
        }
        .payment-modal-options button.maya-option:hover {
            border-color: var(--pink);
            color: var(--pink);
        }

        /* ---- Review / confirm pop-up ---- */
        .review-list {
            margin-top: 20px;
            text-align: left;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .review-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 8px;
        }
        .review-row span:first-child {
            color: var(--muted-foreground);
        }
        .review-row span:last-child {
            color: var(--foreground);
            font-weight: 600;
            text-align: right;
        }
        .review-actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
        }
        .review-actions button {
            flex: 1;
            border-radius: 6px;
            padding: 12px 0;
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            cursor: pointer;
            border: none;
        }
        .review-cancel {
            background-color: var(--muted);
            color: var(--foreground);
            border: 1px solid var(--border) !important;
        }
        .review-confirm {
            background-color: var(--pink);
            color: #000000;
        }
    </style>
</head>
<body>

    <?php if (!$success): ?>
    <!-- Payment method pop-up: blocks the form until a choice is made -->
    <div class="modal-overlay" id="paymentModalOverlay">
        <div class="modal-box">
            <h2>Where will you pay?</h2>
            <p>Choose a payment method to continue booking.</p>
            <div class="payment-modal-options">
                <button type="button" class="gcash-option" onclick="choosePaymentMethod('gcash', 'GCash')">GCash</button>
                <button type="button" class="maya-option" onclick="choosePaymentMethod('maya', 'Maya')">Maya</button>
            </div>
        </div>
    </div>

    <!-- Review pop-up: shown right before the booking is actually submitted -->
    <div class="modal-overlay" id="reviewModalOverlay" style="display: none;">
        <div class="modal-box">
            <h2>Confirm Your Booking</h2>
            <p>Please double check the details below before booking.</p>
            <div class="review-list">
                <div class="review-row"><span>Plan</span><span id="reviewPlan">—</span></div>
                <div class="review-row"><span>Date</span><span id="reviewDate">—</span></div>
                <div class="review-row"><span>Time</span><span id="reviewTime">—</span></div>
                <div class="review-row"><span>Stations</span><span id="reviewStations">—</span></div>
                <div class="review-row"><span>Payment method</span><span id="reviewMethod">—</span></div>
            </div>
            <div class="review-actions">
                <button type="button" class="review-cancel" onclick="closeReviewModal()">Go Back</button>
                <button type="button" class="review-confirm" onclick="submitBooking()">Confirm Booking</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="auth-wrap brand-grid">
        <div class="auth-card">
            <h1 class="auth-title">Book a Seat</h1>
            <p class="auth-sub">Hi <?php echo htmlspecialchars($_SESSION['full_name']); ?>, reserve your gaming station below.</p>

            <?php if ($success): ?>
                <div class="auth-success">
                    <p><strong>Booking submitted!</strong></p>
                    <p>Station(s) reserved: <strong>PC <?php echo implode(', PC ', $successStations); ?></strong></p>
                    <p>
                        To confirm your seat, please send your payment via
                        <strong><?php echo htmlspecialchars($methodLabels[$successMethod]); ?></strong> to
                        <strong><?php echo htmlspecialchars($successMethod === 'gcash' ? CAFE_GCASH_NUMBER : CAFE_MAYA_NUMBER); ?></strong>.
                        Our staff will verify and confirm your booking shortly.
                    </p>
                </div>
                <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
            <?php else: ?>

                <?php if (!empty($errors)): ?>
                    <div class="auth-errors">
                        <?php foreach ($errors as $error): ?>
                            <p>• <?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="booking.php" novalidate id="bookingForm">
                    <input type="hidden" name="payment_method" id="paymentMethodInput" value="<?php echo htmlspecialchars($old['payment_method']); ?>">
                    <input type="hidden" name="selected_stations" id="selectedStationsInput" value="">

                    <div class="auth-field">
                        <label for="plan">Plan</label>
                        <select id="plan" name="plan" required>
                            <option value="" disabled <?php echo $old['plan'] === '' ? 'selected' : ''; ?>>Select a plan</option>
                            <?php foreach ($allowedPlans as $planOption): ?>
                                <option value="<?php echo htmlspecialchars($planOption); ?>" <?php echo $old['plan'] === $planOption ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($planOption); ?> (₱<?php echo number_format($planPrices[$planOption], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="auth-row">
                        <div class="auth-field">
                            <label for="booking_date">Date</label>
                            <input type="date" id="booking_date" name="booking_date" value="<?php echo htmlspecialchars($old['date']); ?>" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="auth-field">
                            <label for="booking_time">Time</label>
                            <input type="time" id="booking_time" name="booking_time" value="<?php echo htmlspecialchars($old['time']); ?>" required>
                        </div>
                    </div>

                    <div class="auth-field">
                        <label>Select Your Station(s)</label>
                        <div class="station-picker" id="stationPicker">
                            <?php for ($n = 1; $n <= TOTAL_STATIONS; $n++): ?>
                                <button type="button" class="station-btn" data-station="<?php echo $n; ?>" onclick="toggleStation(<?php echo $n; ?>)">PC <?php echo $n; ?></button>
                            <?php endfor; ?>
                        </div>
                        <div class="station-legend">
                            <span><span class="swatch available"></span> Available</span>
                            <span><span class="swatch selected"></span> Selected</span>
                            <span><span class="swatch taken"></span> Booked</span>
                        </div>
                        <p class="station-hint">Pick a date and time first to see which PCs are free for that slot.</p>
                    </div>

                    <div class="payment-picked-row">
                        <span>Paying via: <strong id="paymentMethodLabel"><?php echo htmlspecialchars($methodLabels[$old['payment_method']] ?? 'Not selected'); ?></strong></span>
                        <button type="button" onclick="openPaymentModal()">Change</button>
                    </div>

                    <button type="button" class="auth-submit" id="reviewBtn" onclick="openReviewModal()">Proceed to Book</button>
                </form>

                <p class="auth-footer"><a href="../homepage/index.php">← Back to homepage</a></p>
                <p class="auth-footer" style="margin-top: 8px; font-size: 12px;">
                    <a href="../account/delete_account.php" style="color: var(--muted-foreground);">Delete my account</a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$success): ?>
    <script>

        const reservedSlots = <?php echo json_encode($reservedSlots); ?>;

        const paymentOverlay = document.getElementById('paymentModalOverlay');
        const reviewOverlay  = document.getElementById('reviewModalOverlay');
        const methodInput    = document.getElementById('paymentMethodInput');
        const methodLabel    = document.getElementById('paymentMethodLabel');
        const reviewBtn      = document.getElementById('reviewBtn');
        const form           = document.getElementById('bookingForm');
        const stationsInput  = document.getElementById('selectedStationsInput');
        const dateInput      = document.getElementById('booking_date');
        const timeInput      = document.getElementById('booking_time');

        const methodDisplayNames = { gcash: 'GCash', maya: 'Maya' };
        let selectedStations = [];

        function choosePaymentMethod(value, label) {
            methodInput.value = value;
            methodLabel.textContent = label;
            paymentOverlay.style.display = 'none';
            reviewBtn.disabled = false;
        }

        function openPaymentModal() {
            paymentOverlay.style.display = 'flex';
        }

        if (methodInput.value) {
            paymentOverlay.style.display = 'none';
        } else {
            reviewBtn.disabled = true;
        }

        function refreshStationPicker() {
            const date = dateInput.value;
            const time = timeInput.value;

            const takenForSlot = new Set(
                reservedSlots
                    .filter(r => r.date === date && r.time === time)
                    .map(r => r.station)
            );

            document.querySelectorAll('.station-btn').forEach(btn => {
                const stationNumber = parseInt(btn.dataset.station, 10);
                const isTaken = date && time && takenForSlot.has(stationNumber);

                if (isTaken) {
                    selectedStations = selectedStations.filter(s => s !== stationNumber);
                    btn.classList.remove('selected');
                    btn.disabled = true;
                } else {
                    btn.disabled = false;
                    btn.classList.toggle('selected', selectedStations.includes(stationNumber));
                }
            });

            stationsInput.value = selectedStations.join(',');
        }

        function toggleStation(stationNumber) {
            if (!dateInput.value || !timeInput.value) {
                alert('Please pick a date and time first.');
                return;
            }

            const index = selectedStations.indexOf(stationNumber);
            if (index === -1) {
                selectedStations.push(stationNumber);
            } else {
                selectedStations.splice(index, 1);
            }
            refreshStationPicker();
        }

        dateInput.addEventListener('change', refreshStationPicker);
        timeInput.addEventListener('change', refreshStationPicker);

        function openReviewModal() {
            if (!form.reportValidity()) {
                return;
            }
            if (!methodInput.value) {
                openPaymentModal();
                return;
            }
            if (selectedStations.length === 0) {
                alert('Please select at least one PC station.');
                return;
            }

            const plan = document.getElementById('plan').value;
            const date = dateInput.value;
            const time = timeInput.value;

            document.getElementById('reviewPlan').textContent     = plan || '—';
            document.getElementById('reviewDate').textContent     = date || '—';
            document.getElementById('reviewTime').textContent     = time || '—';
            document.getElementById('reviewStations').textContent = 'PC ' + selectedStations.join(', PC ');
            document.getElementById('reviewMethod').textContent   = methodDisplayNames[methodInput.value] || '—';

            reviewOverlay.style.display = 'flex';
        }

        function closeReviewModal() {
            reviewOverlay.style.display = 'none';
        }

        function submitBooking() {
            form.submit();
        }
    </script>
    <?php endif; ?>
</body>
</html>