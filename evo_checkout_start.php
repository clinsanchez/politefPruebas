<?php
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Variables desde .env
$gatewayHost = $_ENV['EVO_GATEWAY_HOST'] ?? 'evopaymentsmexico.gateway.mastercard.com';
$apiVersion  = $_ENV['EVO_API_VERSION'] ?? '72';
$merchantId  = $_ENV['EVO_MERCHANT_ID'] ?? '';
$apiUsername = $_ENV['EVO_API_USERNAME'] ?? '';
$apiPassword = $_ENV['EVO_API_PASSWORD'] ?? '';
$currency    = $_ENV['EVO_CURRENCY'] ?? 'MXN';
$operation   = $_ENV['EVO_OPERATION'] ?? 'PURCHASE';

// Sanitizar monto
$amount = $_POST['amount'] ?? '0';
$amount = preg_replace('/[^\d.]/', '', $amount); // solo dígitos y punto
$amount = number_format((float)$amount, 2, '.', '');

if ($amount <= 0) {
    die("Error: Monto inválido. No se puede iniciar el pago.");
}

// Orden ID <= 15 caracteres
$orderId = substr($_POST['reference'] ?? uniqid('O'), 0, 15);

// Guardar en sesión para mostrar en pay.php
session_start();
$_SESSION['amount']   = $amount;
$_SESSION['order_id'] = $orderId;

// Payload
$payload = [
    "apiOperation" => "INITIATE_CHECKOUT",
    "interaction" => [
        "operation" => $operation,
        "merchant" => [
            "name" => "Instituto Politécnico de la Frontera"
        ]
    ],
    "order" => [
        "id" => $orderId,
        "amount" => $amount,
        "currency" => $currency
    ]
];

// cURL
$url = "https://{$gatewayHost}/api/rest/version/{$apiVersion}/merchant/{$merchantId}/session";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_USERPWD, "{$apiUsername}:{$apiPassword}");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200) {
    die("Error EVO API HTTP {$httpcode}: {$response}");
}

$data = json_decode($response, true);
$sessionId = $data['session']['id'] ?? '';

header("Location: pay.php?sessionId={$sessionId}&order_id={$orderId}");
exit;
