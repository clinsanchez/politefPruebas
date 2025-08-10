<?php
error_reporting(E_ALL); ini_set('display_errors',1);

/**
 * diag_auth.php — prueba de autenticación EVO/MPGS
 * - Prueba INITIATE_CHECKOUT contra 1) test-gateway.mastercard.com y 2) evopaymentsmexico.gateway.mastercard.com
 * - NO imprime la contraseña.
 */

$merchantId = isset($_GET['m']) ? $_GET['m'] : 'TEST1198311';
$username   = 'merchant.'.$merchantId;
$password   = getenv('EVO_API_PASSWORD') ?: 'password_025'; // cambia si ya definiste la API password real
$version    = isset($_GET['v']) ? $_GET['v'] : '72';
$amount     = '1.00';
$currency   = 'MXN';
$orderId    = 'O'.substr(md5(uniqid('',true)),0,10);

$hosts = [
  'test-gateway.mastercard.com',
  'evopaymentsmexico.gateway.mastercard.com',
];

$payload = [
  'apiOperation' => 'INITIATE_CHECKOUT',
  'interaction' => [
    'operation' => 'AUTHORIZE',
    'returnUrl' => 'https://example.com/return',
    'cancelUrl' => 'https://example.com/cancel',
    'merchant'  => ['name'=>'Diag']
  ],
  'order' => [
    'amount' => $amount,
    'currency' => $currency,
    'id' => $orderId,
    'description' => 'Diag test'
  ]
];

echo "<pre>";
echo "Merchant: {$merchantId}\n";
echo "Username: {$username}\n";
echo "API Version: {$version}\n";
echo "OrderId: {$orderId}\n";
echo "Testing hosts...\n\n";

foreach ($hosts as $host) {
  $url = "https://{$host}/api/rest/version/{$version}/merchant/{$merchantId}/session";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  echo "Host: {$host}\n";
  if ($err) {
    echo "  cURL error: {$err}\n\n";
    continue;
  }
  echo "  HTTP {$code}\n";
  echo "  Body: {$resp}\n\n";
}
echo "</pre>";
