<?php
// ============================================================
//  StockFlow — Ticket / comprobante imprimible
//  Archivo: modules/ventas/ticket.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

// ── Configuración de la empresa ──────────────────────────────
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Datos ────────────────────────────────────────────────────
$venta = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT v.*, c.nombre AS cliente, c.telefono AS cliente_tel,
           u.nombre AS vendedor
    FROM ventas v
    JOIN clientes c ON v.cliente_id = c.id
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = $id
"));
if (!$venta) { header("Location: index.php"); exit(); }

$detalle = mysqli_query($conn, "
    SELECT dv.*, p.nombre AS producto, p.codigo
    FROM detalle_venta dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = $id
    ORDER BY dv.id ASC
");

$comp = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM comprobantes WHERE venta_id = $id LIMIT 1"
));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?> — StockFlow</title>
    <style>
        * { box-sizing:border-box; margin:0; padding:0; }

        body {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            background: #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 16px;
            min-height: 100vh;
        }

        /* Botones de acción (no se imprimen) */
        .no-print {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn {
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
            text-decoration: none;
            display: inline-block;
        }
        .btn-print  { background: #1F5FA5; color: #fff; }
        .btn-back   { background: #fff; color: #333; border: 1px solid #ccc; }

        /* Ticket */
        .ticket {
            background: #fff;
            width: 100%;
            max-width: 320px;
            padding: 20px 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,.15);
            border-radius: 4px;
        }

        .ticket__header { text-align: center; margin-bottom: 14px; }
        .ticket__empresa { font-size: 16px; font-weight: bold; letter-spacing: 1px; }
        .ticket__subtitulo { font-size: 11px; color: #666; margin-top: 2px; }
        .ticket__ruc { font-size: 11px; color: #444; margin-top: 2px; }
        .ticket__dir { font-size: 10px; color: #666; margin-top: 2px; }

        .divider { border: none; border-top: 1px dashed #999; margin: 10px 0; }
        .divider-solid { border: none; border-top: 1px solid #333; margin: 10px 0; }

        .ticket__tipo {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 6px 0;
        }
        .ticket__num {
            text-align: center;
            font-size: 12px;
            color: #444;
        }

        .info-row { display:flex; justify-content:space-between; margin: 3px 0; font-size:11px; }
        .info-row span:first-child { color:#666; }
        .info-row span:last-child  { text-align:right; max-width:60%; }

        /* Tabla de productos */
        .productos-header {
            display: grid;
            grid-template-columns: 1fr 36px 70px;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 0;
            border-bottom: 1px solid #999;
            margin-bottom: 4px;
        }
        .producto-fila {
            margin: 4px 0;
        }
        .producto-nombre { font-size:12px; font-weight:bold; }
        .producto-detalle {
            display: grid;
            grid-template-columns: 1fr 36px 70px;
            font-size: 11px;
            color: #444;
        }

        /* Totales */
        .totales { margin-top: 6px; }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            padding: 2px 0;
        }
        .total-row--final {
            font-size: 15px;
            font-weight: bold;
            padding: 6px 0;
            border-top: 2px solid #333;
            margin-top: 4px;
        }

        .ticket__footer { text-align:center; font-size:11px; color:#666; margin-top:12px; line-height:1.6; }

        /* Impresión */
        @media print {
            body     { background: white; padding: 0; }
            .no-print { display: none !important; }
            .ticket  { box-shadow: none; max-width: 100%; border-radius: 0; }
        }
    </style>
</head>
<body>

<!-- Botones (no se imprimen) -->
<div class="no-print">
    <button onclick="window.print()" class="btn btn-print">Imprimir</button>
    <a href="detalle.php?id=<?= $id ?>" class="btn btn-back">← Volver al detalle</a>
    <a href="index.php" class="btn btn-back">Lista de ventas</a>
</div>

<!-- Ticket -->
<div class="ticket">

    <!-- Encabezado de la empresa -->
    <div class="ticket__header">
        <div class="ticket__empresa">
            <?= htmlspecialchars($config['empresa_nombre'] ?? 'StockFlow') ?>
        </div>
        <?php if (!empty($config['empresa_ruc'])): ?>
        <div class="ticket__ruc">RUC: <?= htmlspecialchars($config['empresa_ruc']) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['empresa_direccion'])): ?>
        <div class="ticket__dir"><?= htmlspecialchars($config['empresa_direccion']) ?></div>
        <?php endif; ?>
        <?php if (!empty($config['empresa_telefono'])): ?>
        <div class="ticket__dir">Tel: <?= htmlspecialchars($config['empresa_telefono']) ?></div>
        <?php endif; ?>
    </div>

    <hr class="divider-solid">

    <!-- Tipo y número de comprobante -->
    <div class="ticket__tipo">
        <?= strtoupper($comp['tipo'] ?? 'ticket') ?>
    </div>
    <div class="ticket__num">
        N° <?= htmlspecialchars($comp['numero'] ?? str_pad($id,6,'0',STR_PAD_LEFT)) ?>
    </div>

    <hr class="divider">

    <!-- Datos de la venta -->
    <div class="info-row">
        <span>Fecha:</span>
        <span><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></span>
    </div>
    <div class="info-row">
        <span>Cliente:</span>
        <span><?= htmlspecialchars($venta['cliente']) ?></span>
    </div>
    <div class="info-row">
        <span>Vendedor:</span>
        <span><?= htmlspecialchars($venta['vendedor']) ?></span>
    </div>
    <div class="info-row">
        <span>Pago:</span>
        <span><?= ucfirst($venta['metodo_pago']) ?></span>
    </div>

    <hr class="divider">

    <!-- Productos -->
    <div class="productos-header">
        <span>Producto</span>
        <span style="text-align:center">Cant</span>
        <span style="text-align:right">Total</span>
    </div>

    <?php while ($d = mysqli_fetch_assoc($detalle)): ?>
    <div class="producto-fila">
        <div class="producto-nombre"><?= htmlspecialchars($d['producto']) ?></div>
        <div class="producto-detalle">
            <span><?= $moneda ?> <?= number_format($d['precio_unitario'],2) ?> c/u</span>
            <span style="text-align:center"><?= $d['cantidad'] ?></span>
            <span style="text-align:right"><?= $moneda ?> <?= number_format($d['subtotal'],2) ?></span>
        </div>
    </div>
    <?php endwhile; ?>

    <hr class="divider">

    <!-- Totales -->
    <div class="totales">
        <div class="total-row">
            <span>Subtotal:</span>
            <span><?= $moneda ?> <?= number_format($venta['subtotal'],2) ?></span>
        </div>
        <div class="total-row">
            <span>IGV (18%):</span>
            <span><?= $moneda ?> <?= number_format($venta['igv'],2) ?></span>
        </div>
        <div class="total-row total-row--final">
            <span>TOTAL:</span>
            <span><?= $moneda ?> <?= number_format($venta['total'],2) ?></span>
        </div>
    </div>

    <hr class="divider">

    <!-- Pie del ticket -->
    <div class="ticket__footer">
        <p>¡Gracias por su compra!</p>
        <?php if (!empty($config['empresa_email'])): ?>
        <p><?= htmlspecialchars($config['empresa_email']) ?></p>
        <?php endif; ?>
        <p style="margin-top:6px; font-size:10px; color:#aaa">
            Generado por StockFlow
        </p>
    </div>

</div><!-- .ticket -->

</body>
</html>
