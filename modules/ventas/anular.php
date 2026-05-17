<?php
// ============================================================
//  StockFlow — Anular venta
//  Archivo: modules/ventas/anular.php
//  Solo admin. Restaura el stock de los productos.
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

$venta = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM ventas WHERE id=$id AND estado='completada'"
));
if (!$venta) {
    header("Location: index.php?msg=Venta+no+encontrada+o+ya+anulada");
    exit();
}

// Obtener detalle para restaurar stock
$detalle = mysqli_query($conn,
    "SELECT producto_id, cantidad FROM detalle_venta WHERE venta_id=$id"
);

// Usar transacción para que todo ocurra junto o nada
mysqli_begin_transaction($conn);
try {
    // 1. Cambiar estado de la venta a anulada
    $stmt = mysqli_prepare($conn, "UPDATE ventas SET estado='anulada' WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    // 2. Restaurar stock de cada producto
    while ($d = mysqli_fetch_assoc($detalle)) {
        $stmt2 = mysqli_prepare($conn,
            "UPDATE productos SET stock_actual = stock_actual + ? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt2, 'ii', $d['cantidad'], $d['producto_id']);
        mysqli_stmt_execute($stmt2);
    }

    mysqli_commit($conn);
    header("Location: index.php?msg=Venta+anulada+y+stock+restaurado");
} catch (Exception $e) {
    mysqli_rollback($conn);
    header("Location: detalle.php?id=$id&msg=Error+al+anular+la+venta");
}
exit();
?>
