<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['password'])) {
    die('No hay sesión activa. Por favor, inicia sesión primero.');
}

// Usuario (gr_no) → primera letra minúscula
$raw_username = $_SESSION['username'];
$username = strtolower(substr($raw_username, 0, 1)) . substr($raw_username, 1);

// Contraseña (CURP) → primera letra minúscula + resto + #
$raw_curp = $_SESSION['password'];
$password = strtolower(substr($raw_curp, 0, 1)) . substr($raw_curp, 1) . '#';

echo "<h3>Depuración de autologin:</h3>";
echo "Usuario enviado: <strong>" . htmlspecialchars($username) . "</strong><br>";
echo "Contraseña enviada: <strong>" . htmlspecialchars($password) . "</strong><br><br>";

$moodle_login_url = 'https://plataforma.politefjrz.com/login/index.php';

echo <<<HTML
<form method="post" action="$moodle_login_url">
  <input type="hidden" name="username" value="$username">
  <input type="hidden" name="password" value="$password">
  <input type="submit" value="Hacer login manual con estos datos">
</form>
HTML;
?>
