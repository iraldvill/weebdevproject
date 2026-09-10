<?php
session_start();
require '../database/config.php';


if (empty($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../login/login.php');
    exit;
}

const TOTAL_STATIONS = 30; 

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

$old = [
    'plan'            => $selectedPlan,
    'date'            => '',
    'time'            => '',
    'station_numbers' => [],
    'payment_method'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan          = $_POST['plan'] ?? '';
    $date          = $_POST['booking_date'] ?? '';
    $time          = $_POST['booking_time'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';

    $rawStations = trim($_POST['station_numbers'] ?? '');
    $stationNumbers = [];
    if ($rawStations !== '') {
        foreach (explode(',', $rawStations) as $piece) {
            $n = (int)trim($piece);
            if ($n >= 1 && $n <= TOTAL_STATIONS && !in_array($n, $stationNumbers, true)) {
                $stationNumbers[] = $n;
            }
        }
    }
    sort($stationNumbers);

    $old = [
        'plan'            => $plan,
        'date'            => $date,
        'time'            => $time,
        'station_numbers' => $stationNumbers,
        'payment_method'  => $paymentMethod,
    ];

    // ---- Validation 
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

    if (empty($stationNumbers)) {
        $errors[] = 'Please select at least one station from the grid.';
    }

    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $errors[] = 'Please choose where you will pay.';
    }


    if (empty($errors)) {
        $amountCentavos = $planPrices[$plan] * 100;
        $stations = count($stationNumbers);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO bookings (user_id, plan_name, booking_date, booking_time, stations, amount, payment_method)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$_SESSION['user_id'], $plan, $date, $time, $stations, $amountCentavos, $paymentMethod]);
            $bookingId = $pdo->lastInsertId();

            $claim = $pdo->prepare(
                'INSERT INTO booking_stations (booking_id, station_number, booking_date, booking_time)
                 VALUES (?, ?, ?, ?)'
            );
            foreach ($stationNumbers as $stationNumber) {
                $claim->execute([$bookingId, $stationNumber, $date, $time . ':00']);
            }

            $pdo->commit();

            $success       = true;
            $successMethod = $paymentMethod;
            $old = ['plan' => '', 'date' => '', 'time' => '', 'station_numbers' => [], 'payment_method' => ''];
        } catch (PDOException $e) {
            $pdo->rollBack();

            if ($e->getCode() === '23000') {
                $errors[] = 'This station has been booked! Try something else.';
            } else {
                $errors[] = 'Something went wrong saving your booking. Please try again.';
            }
        }
    }
}

$methodLabels = ['gcash' => 'GCash', 'maya' => 'Maya'];
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
            max-width: 480px;
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

        /* ---- Station availability grid ---- */
        .station-grid-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .station-grid-head .picked-count {
            font-size: 12px;
            color: var(--cerulean);
        }
        .station-legend {
            margin-top: 8px;
            display: flex;
            gap: 16px;
            font-size: 11px;
            color: var(--muted-foreground);
        }
        .station-legend span {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 3px;
            display: inline-block;
        }
        .legend-dot.available { background-color: var(--muted); border: 1px solid var(--border); }
        .legend-dot.selected { background-color: var(--cerulean); }
        .legend-dot.booked { background-color: var(--pink); opacity: 0.5; }

        .station-grid {
            margin-top: 12px;
            max-height: 220px;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background-color: var(--muted);
        }
        .station-btn {
            font-family: var(--font-display);
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            border: 1px solid var(--border);
            background-color: var(--card);
            color: var(--foreground);
            padding: 10px 0;
            cursor: pointer;
            transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
        }
        .station-btn:hover:not(:disabled) {
            border-color: var(--cerulean);
            color: var(--cerulean);
        }
        .station-btn.selected {
            background-color: var(--cerulean);
            border-color: var(--cerulean);
            color: #000000;
        }
        .station-btn:disabled,
        .station-btn.booked {
            background-color: var(--card);
            border-color: var(--border);
            color: var(--pink);
            opacity: 0.45;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        .station-warning {
            margin-top: 10px;
            font-size: 12px;
            color: var(--pink);
            min-height: 16px;
        }
        .station-hint {
            margin-top: 8px;
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
                    <input type="hidden" name="station_numbers" id="stationNumbersInput" value="<?php echo htmlspecialchars(implode(',', $old['station_numbers'])); ?>">

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
                        <div class="station-grid-head">
                            <label style="margin:0;">Pick your station(s)</label>
                            <span class="picked-count" id="pickedCount">0 selected</span>
                        </div>

                        <div class="station-grid" id="stationGrid">
                            <?php for ($n = 1; $n <= TOTAL_STATIONS; $n++): ?>
                                <button
                                    type="button"
                                    class="station-btn<?php echo in_array($n, $old['station_numbers'], true) ? ' selected' : ''; ?>"
                                    data-station="<?php echo $n; ?>"
                                    onclick="toggleStation(<?php echo $n; ?>)"
                                >PC <?php echo $n; ?></button>
                            <?php endfor; ?>
                        </div>

                        <div class="station-legend">
                            <span><span class="legend-dot available"></span> Available</span>
                            <span><span class="legend-dot selected"></span> Selected</span>
                            <span><span class="legend-dot booked"></span> Booked</span>
                        </div>

                        <p class="station-hint">Pick a date and time first — availability updates automatically.</p>
                        <p class="station-warning" id="stationWarning"></p>
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
        const paymentOverlay   = document.getElementById('paymentModalOverlay');
        const reviewOverlay    = document.getElementById('reviewModalOverlay');
        const methodInput      = document.getElementById('paymentMethodInput');
        const methodLabel      = document.getElementById('paymentMethodLabel');
        const reviewBtn        = document.getElementById('reviewBtn');
        const form             = document.getElementById('bookingForm');
        const dateInput        = document.getElementById('booking_date');
        const timeInput        = document.getElementById('booking_time');
        const stationInput     = document.getElementById('stationNumbersInput');
        const pickedCountLabel = document.getElementById('pickedCount');
        const stationWarning   = document.getElementById('stationWarning');

        const methodDisplayNames = { gcash: 'GCash', maya: 'Maya' };


        let selected = new Set(
            (stationInput.value || '')
                .split(',')
                .map(s => parseInt(s, 10))
                .filter(n => !isNaN(n))
        );
        let bookedForSlot = new Set();

        function refreshStationUI() {
            document.querySelectorAll('.station-btn').forEach(btn => {
                const num = parseInt(btn.dataset.station, 10);
                const isBooked = bookedForSlot.has(num);

                btn.classList.toggle('booked', isBooked);
                btn.disabled = isBooked;

                if (isBooked) {
                    btn.classList.remove('selected');
                    selected.delete(num);
                } else {
                    btn.classList.toggle('selected', selected.has(num));
                }
            });

            stationInput.value = Array.from(selected).sort((a, b) => a - b).join(',');
            pickedCountLabel.textContent = selected.size + ' selected';
        }

        function toggleStation(num) {
            if (bookedForSlot.has(num)) {
                stationWarning.textContent = 'This station has been booked! Try something else.';
                return;
            }
            stationWarning.textContent = '';

            if (selected.has(num)) {
                selected.delete(num);
            } else {
                selected.add(num);
            }
            refreshStationUI();
        }

        async function fetchAvailability() {
            const date = dateInput.value;
            const time = timeInput.value;
            if (!date || !time) {
                return;
            }

            try {
                const res = await fetch(`check_availability.php?date=${encodeURIComponent(date)}&time=${encodeURIComponent(time)}`);
                const data = await res.json();
                bookedForSlot = new Set(data.booked || []);
                refreshStationUI();
            } catch (err) {
 
                console.error('Could not check station availability', err);
            }
        }

        dateInput.addEventListener('change', fetchAvailability);
        timeInput.addEventListener('change', fetchAvailability);

        if (dateInput.value && timeInput.value) {
            fetchAvailability();
        } else {
            refreshStationUI();
        }

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

        function openReviewModal() {

            if (!form.reportValidity()) {
                return;
            }
            if (selected.size === 0) {
                stationWarning.textContent = 'Please select at least one station.';
                return;
            }
            if (!methodInput.value) {
                openPaymentModal();
                return;
            }

            const plan = document.getElementById('plan').value;
            const date = dateInput.value;
            const time = timeInput.value;
            const stationList = Array.from(selected).sort((a, b) => a - b).map(n => 'PC ' + n).join(', ');

            document.getElementById('reviewPlan').textContent     = plan || '—';
            document.getElementById('reviewDate').textContent     = date || '—';
            document.getElementById('reviewTime').textContent     = time || '—';
            document.getElementById('reviewStations').textContent = stationList || '—';
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