<?php
// ============================================================
//  StockFlow — Entrada principal
//  Archivo: index.php (raíz del proyecto)
// ============================================================
session_start();

if (isset($_SESSION['usuario_id'])) {
    header("Location: /stockflow/dashboard.php");
} else {
    header("Location: /stockflow/modules/auth/login.php");
}
exit();
?>