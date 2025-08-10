<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

define('ODOO_URL', $_ENV['ODOO_URL']);
define('ODOO_DB', $_ENV['ODOO_DB']);
define('ODOO_USER', $_ENV['ODOO_USER']);
define('ODOO_PASS', $_ENV['ODOO_PASS']);

// --- Definir constantes para la base de datos local (Hostinger) ---
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_DATABASE', $_ENV['DB_DATABASE']);
define('DB_USERNAME', $_ENV['DB_USERNAME']);
define('DB_PASSWORD', $_ENV['DB_PASSWORD']);

// --- Configuración de la conexión a la base de datos local (PDO) ---
$charset = 'utf8mb4';
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_DATABASE . ";charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
?>
