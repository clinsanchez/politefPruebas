<?php
session_start();

// Limpiar todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Borrar la cookie de sesión si existiera
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirigir al login
header("Location: index.php");
exit;
