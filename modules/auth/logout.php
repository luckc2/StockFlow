<?php
// modules/auth/logout.php — StockFlow
require_once '../../config/session.php';

// Destruir todos los datos de sesión
$_SESSION = [];

// Eliminar la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header("Location: /stockflow/modules/auth/login.php");
exit();
?>
