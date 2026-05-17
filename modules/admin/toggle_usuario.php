<?php
// ============================================================
//  StockFlow — Activar / desactivar usuario
//  Archivo: modules/admin/toggle_usuario.php
//  Solo admin. No tiene vista propia, solo procesa y redirige.
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

$id = (int)($_GET['id'] ?? 0);

// Nunca se puede desactivar a uno mismo
if ($id && $id !== (int)$_SESSION['usuario_id']) {
    $stmt = mysqli_prepare($conn,
        "UPDATE usuarios SET activo = IF(activo = 1, 0, 1) WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $msg = "Usuario+actualizado+correctamente";
} else {
    $msg = "Accion+no+permitida";
}

header("Location: usuarios.php?msg=" . $msg);
exit();
?>
