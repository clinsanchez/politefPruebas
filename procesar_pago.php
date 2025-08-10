<?php
session_start();

// Muestra todos los errores para un diagnóstico claro durante las pruebas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- PASO 1: CONFIGURACIÓN DE EVO PAYMENTS ---
$merchantId = 'TEST1198311'; // Tu ID de Establecimiento (Merchant ID).

// ¡IMPORTANTE! Esta es la contraseña generada en "Admin" -> "Integration Settings"
$apiPassword = '1915e67ca347631201dc49fbb1def021'; // Tu "Password 2"

// URL del Gateway
$gatewayUrl = "https://evopaymentsmexico.gateway.mastercard.com/api/rest/version/72/merchant/{$merchantId}/session";

// --- PASO 2: VERIFICACIÓN DE DATOS ---
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['amount']) || !isset($_POST['order_reference'])) {
    header("Location: pagos.php");
    exit();
}

$orderAmount = $_POST['amount'];
$orderReference = $_POST['order_reference'];
$orderDescription = $_POST['order_description'];
$orderId = 'ORD-' . preg_replace('/[^A-Za-z0-9\-]/', '', $orderReference) . '-' . time();
$returnUrl = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/pago_respuesta.php';

// --- PASO 3: CONSTRUIR LA SOLICITUD PARA LA API ---
$requestData = [
    'apiOperation' => 'INITIATE_CHECKOUT',
    'order' => [ 'id' => $orderId, 'amount' => $orderAmount, 'currency' => 'MXN', 'description' => $orderDescription ],
    'interaction' => [ 'operation' => 'PURCHASE', 'returnUrl' => $returnUrl, 'merchant' => [ 'name' => 'Politecnico de la Frontera' ] ]
];

// --- PASO 4: ENVIAR LA SOLICITUD AL GATEWAY (CON AUTENTICACIÓN CORRECTA) ---

// 1. Crear la cadena de autenticación según la documentación: "merchant.<ID>:<contraseña>"
// Esta contraseña de integración está asociada directamente con el Merchant ID.
$authString = 'merchant.' . $merchantId . ':' . $apiPassword;

// 2. Codificar la cadena en Base64
$base64AuthString = base64_encode($authString);

// 3. Preparar los encabezados HTTP
$headers = [
    'Content-Type: application/json',
    'Authorization: Basic ' . $base64AuthString
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$responseJson = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// --- PASO 5: PROCESAR LA RESPUESTA ---
if ($curlError) {
    $error_message = "Error de comunicación cURL: " . $curlError;
    header("Location: pagos.php?error=" . urlencode($error_message));
    exit();
}

$responseData = json_decode($responseJson, true);

if (isset($responseData['result']) && $responseData['result'] === 'SUCCESS' && isset($responseData['session']['id'])) {
    $sessionId = $responseData['session']['id'];
    
    $_SESSION['payment_session'] = [
        'sessionId' => $sessionId, 'orderId' => $orderId,
        'amount' => $orderAmount, 'successIndicator' => $responseData['successIndicator']
    ];

    $checkoutUrl = 'https://evopaymentsmexico.gateway.mastercard.com/checkout/entry/' . $sessionId;
    header('Location: ' . $checkoutUrl);
    exit();

} else {
    $errorCode = $responseData['error']['cause'] ?? 'N/A';
    $errorExplanation = $responseData['error']['explanation'] ?? 'Respuesta inválida del gateway.';
    $error_message = "Error del Gateway ({$httpCode} - {$errorCode}): {$errorExplanation}";
    header("Location: pagos.php?error=" . urlencode($error_message));
    exit();
}
?>
