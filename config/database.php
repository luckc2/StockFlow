<?php
//  StockFlow — Conexión a la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'stockflow');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS,DB_NAME);

// Verificar que conectó bien
if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

// Importante: le decimos que use UTF-8 para que las tildes y ñ no se dañen
mysqli_set_charset($conn, 'utf8mb4');
?>