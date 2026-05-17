<?php
require_once '../../config/session.php';
require_once '../../config/database.php';
verificarSesion();
if (!tieneRol(['admin','almacen'])) { header("Location: index.php"); exit(); }

$id = (int)($_GET['id'] ?? 0);

if ($id) {
    $stmt = mysqli_prepare($conn, "SELECT id, nombre FROM productos WHERE id=? AND activo=1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $producto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($producto) {
        $stmt = mysqli_prepare($conn, "UPDATE productos SET activo=0 WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $msg = "Producto+eliminado+correctamente";
    } else {
        $msg = "Producto+no+encontrado";
    }
} else {
    $msg = "ID+invalido";
}

header("Location: index.php?msg=" . $msg);
exit();