<?php
session_start();
$sessionId = $_GET['sessionId'] ?? '';
$orderId   = $_GET['order_id'] ?? '';
$amount    = $_SESSION['amount'] ?? '0.00';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago de factura #<?php echo htmlspecialchars($orderId); ?></title>
    <script src="https://evopaymentsmexico.gateway.mastercard.com/checkout/version/72/checkout.js"></script>
</head>
<body>
    <h1>Pago de factura #<?php echo htmlspecialchars($orderId); ?></h1>
    <p>Monto: <strong>$<?php echo number_format($amount, 2); ?> MXN</strong></p>

    <button id="payButton">Pagar ahora</button>

    <script>
    Checkout.configure({
        session: { id: "<?php echo $sessionId; ?>" }
    });

    document.getElementById('payButton').addEventListener('click', function(){
        Checkout.showPaymentPage();
    });
    </script>
</body>
</html>
