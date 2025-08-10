<?php
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['password'])) {
    die('No hay sesión activa. Por favor, inicia sesión primero.');
}

// Usuario (ej. a14649)
$username = $_SESSION['username'];

// CURP ajustada: primera letra en minúscula + resto igual + #
$raw_curp = $_SESSION['password'];
$password = strtolower(substr($raw_curp, 0, 1)) . substr($raw_curp, 1) . '#';

// URL segura
$moodle_login_url = 'https://plataforma.politefjrz.com/login/index.php';

echo <<<HTML
<html>
  <head>
    <meta charset="UTF-8">
    <title>Redirigiendo a Plataforma Digital...</title>
  </head>
  <body onload="document.forms[0].submit()">
    <form method="post" action="$moodle_login_url">
      <input type="hidden" name="username" value="$username">
      <input type="hidden" name="password" value="$password">
      <noscript>
        <input type="submit" value="Ingresar a la plataforma digital">
      </noscript>
    </form>
  </body>
</html>
HTML;
?>
