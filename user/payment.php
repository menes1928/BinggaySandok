<?php
require_once("../classes/database.php");
session_start();
$db = new database();

$sweetAlertConfig = "";

// Get cp_id from GET or SESSION
$cp_id = $_GET['cp_id'] ?? $_SESSION['pending_cp_id'] ?? null;

// Fetch catering package details
$package = null;
if ($cp_id) {
    $package = $db->getCateringPackage($cp_id);
}

if (!$package) {
    die("Invalid catering package.");
}

$cp_price = floatval($package['cp_price']);
$half_price = $cp_price / 2;

// Handle payment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $user_id = $_SESSION['user_id'] ?? null;
    $pay_amount = floatval($_POST['pay_amount']);
    $pay_method = $_POST['pay_method'];
    $pay_date = date('Y-m-d');

    // Determine payment status
    if ($pay_amount >= $cp_price) {
        $pay_status = 'Fully Paid';
    } elseif ($pay_amount >= $half_price) {
        $pay_status = 'Partial';
    } else {
        $pay_status = 'Pending';
    }

    if ($pay_amount < $half_price) {
        $sweetAlertConfig = "
        <script>
        Swal.fire({
            icon: 'warning',
            title: 'Insufficient Downpayment',
            text: 'You must pay at least half of the package price.',
            confirmButtonText: 'OK'
        });
        </script>";
    } else {
        // Save payment to 'payments' table
        $result = $db->savePayment(
            null, // order_id is null for catering
            $cp_id,
            $user_id,
            $pay_date,
            $pay_amount,
            $pay_method,
            $pay_status
        );

        if ($result === true) {
            $sweetAlertConfig = "
            <script>
            Swal.fire({
                icon: 'success',
                title: 'Payment Successful!',
                text: 'Thank you for your payment. We will contact you soon.',
                confirmButtonText: 'OK'
            }).then(() => {
                window.location.href = 'cateringpackages.php';
            });
            </script>";
        } else {
            $sweetAlertConfig = "
            <script>
            Swal.fire({
                icon: 'error',
                title: 'Payment Error',
                text: 'Payment failed. Please try again.',
                confirmButtonText: 'OK'
            });
            </script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catering Package Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/payment.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-body p-5">
                    <h2 class="mb-4 text-center text-success"><i class="fas fa-credit-card me-2"></i>Pay for Catering Package</h2>
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle me-1"></i>
                        <strong>Note:</strong> You are required to pay at least <b>₱<?php echo number_format($half_price, 2); ?></b> (50% of the package price) as a downpayment for ingredient purchases. The rest can be paid upon delivery.
                    </div>
                    <ul class="list-group mb-4">
                        <li class="list-group-item"><b>Package:</b> <?php echo htmlspecialchars($package['cp_name']); ?></li>
                        <li class="list-group-item"><b>Venue:</b> <?php echo htmlspecialchars($package['cp_place']); ?></li>
                        <li class="list-group-item"><b>Date:</b> <?php echo htmlspecialchars($package['cp_date']); ?></li>
                        <li class="list-group-item"><b>Total Price:</b> ₱<?php echo number_format($cp_price, 2); ?></li>
                    </ul>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="pay_method" required>
                                <option value="" disabled selected>Select method</option>
                                <option value="Online">Online</option>
                                <option value="Credit">Credit</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Amount to Pay</label>
                            <input type="number" class="form-control" name="pay_amount" min="<?php echo $half_price; ?>" max="<?php echo $cp_price; ?>" step="0.01" required>
                            <div class="form-text">Minimum downpayment: ₱<?php echo number_format($half_price, 2); ?></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="cateringpackages.php" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" name="pay_now" class="btn btn-success px-4">Pay Now</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo $sweetAlertConfig; ?>
</body>
</html>