<?php
error_reporting(E_ALL); ini_set('display_errors',1);
echo "<h3>Diagnóstico rápido</h3>";
echo "<pre>";
echo "PHP: ".PHP_VERSION."\n";
echo "cURL: ".(function_exists('curl_version') ? 'SI' : 'NO')."\n";
if (function_exists('curl_version')) {
  $v = curl_version();
  echo "  SSL: ".($v['ssl_version'] ?? 'n/a')."\n";
}
echo "OpenSSL: ".(defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'n/a')."\n";
echo "vendor/autoload: ".(file_exists(__DIR__.'/vendor/autoload.php') ? 'existe' : 'no')."\n";
echo ".env: ".(file_exists(__DIR__.'/.env') ? 'existe' : 'no')."\n";
echo "tmp/: ".(is_dir(__DIR__.'/tmp') ? 'existe' : 'no')."\n";
echo "</pre>";
