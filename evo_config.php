<?php
// evo_config.php (defensivo) - EVO Hosted Checkout
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Autoload (opcional)
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Cargar .env si Dotenv existe
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    // safeLoad no truena si falta el archivo
    if (method_exists($dotenv, 'safeLoad')) { $dotenv->safeLoad(); }
    else { $dotenv->load(); }
}

// Helper env con fallback
function envval($k, $default=null) {
    $v = $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k);
    return ($v !== false && $v !== null && $v !== '') ? $v : $default;
}

// Defaults de PRUEBA (puedes sobreescribir con .env)
$EVO_HOST         = rtrim(envval('EVO_GATEWAY_HOST', 'evopaymentsmexico.gateway.mastercard.com'), '/');
$EVO_API_VERSION  = envval('EVO_API_VERSION', '72');
$EVO_MERCHANT_ID  = envval('EVO_MERCHANT_ID', 'TEST1198311');
$EVO_API_USERNAME = envval('EVO_API_USERNAME', 'merchant.TEST1198311');
$EVO_API_PASSWORD = envval('EVO_API_PASSWORD', '1915e67ca347831201dc48fbb1def021');
$EVO_CURRENCY     = envval('EVO_CURRENCY', 'MXN');
$EVO_OPERATION    = envval('EVO_OPERATION', 'PURCHASE'); // AUTHORIZE o 'PAY'

$EVO_API_BASE = "https://{$EVO_HOST}/api/rest/version/{$EVO_API_VERSION}/merchant/{$EVO_MERCHANT_ID}";

// Llamadas a EVO (con errores legibles)
function evo_api_call($method, $path, $payload=null) {
    global $EVO_API_BASE, $EVO_API_USERNAME, $EVO_API_PASSWORD;
    $url = $EVO_API_BASE . $path;
    if (!function_exists('curl_init')) {
        throw new Exception("PHP cURL no está habilitado en el servidor.");
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $EVO_API_USERNAME . ":" . $EVO_API_PASSWORD);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (!is_null($payload)) {
            $json = json_encode($payload);
            if ($json === false) {
                throw new Exception("Error al codificar JSON del payload.");
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        }
    }
    // Opcional: en hosting con certificados atípicos, descomentar siguiente línea para probar
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        throw new Exception("EVO API cURL error ({$code}): ".$err);
    }
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($resp, true);
    if ($http >= 400) {
        // Devuelve el cuerpo crudo para depurar
        throw new Exception("EVO API HTTP {$http}: ".$resp);
    }
    return $data;
}
