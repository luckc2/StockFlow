<?php
// ============================================================
//  StockFlow — Detalle de venta
//  Archivo: modules/ventas/detalle.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

// ── Configuración de moneda ──────────────────────────────────
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Datos de la venta ────────────────────────────────────────
$venta = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        v.*,
        c.nombre    AS cliente,
        c.telefono  AS cliente_tel,
        c.email     AS cliente_email,
        c.direccion AS cliente_dir,
        c.id        AS cliente_id,
        u.nombre    AS vendedor,
        u.rol       AS vendedor_rol
    FROM ventas v
    JOIN clientes c ON v.cliente_id = c.id
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE v.id = $id
"));
if (!$venta) { header("Location: index.php"); exit(); }

// ── Detalle de productos ─────────────────────────────────────
$detalle = mysqli_query($conn, "
    SELECT
        dv.*,
        p.nombre  AS producto,
        p.codigo  AS codigo
    FROM detalle_venta dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = $id
    ORDER BY dv.id ASC
");

// ── Comprobante ──────────────────────────────────────────────
$comp = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM comprobantes WHERE venta_id = $id LIMIT 1"
));

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Venta #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></h1>
        <p class="page-subtitle"><?= date('d/m/Y H:i', strtotime($venta['fecha'])) ?></p>
    </div>
    <div style="display:flex; gap:8px">
        <a href="ticket.php?id=<?= $id ?>" class="btn btn--success-outline" target="_blank">🖨️ Ver ticket</a>
        <?php if ($venta['estado'] === 'completada' && tieneRol(['admin'])): ?>
        <a href="anular.php?id=<?= $id ?>"
           class="btn btn--danger-outline"
           data-confirm="¿Anular esta venta? Se restaurará el stock de los productos.">
           Anular venta
        </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn--ghost">← Volver</a>
    </div>
</div>

<div class="detalle-grid">

    <!-- ── Información general ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Información general</h2>
            <span class="badge badge--<?= $venta['estado'] ?>"><?= ucfirst($venta['estado']) ?></span>
        </div>
        <div class="card__body">
            <div class="info-list">
                <div class="info-row">
                    <span class="info-row__label">Comprobante</span>
                    <span class="info-row__valor text-mono">
                        <?= $comp ? htmlspecialchars($comp['numero']) . ' (' . ucfirst($comp['tipo']) . ')' : '—' ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Fecha y hora</span>
                    <span class="info-row__valor"><?= date('d/m/Y H:i:s', strtotime($venta['fecha'])) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Método de pago</span>
                    <span class="info-row__valor"><?= ucfirst($venta['metodo_pago']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-row__label">Vendedor</span>
                    <span class="info-row__valor">
                        <?= htmlspecialchars($venta['vendedor']) ?>
                        <span class="badge badge--<?= $venta['vendedor_rol'] ?>" style="font-size:.65rem">
                            <?= strtoupper($venta['vendedor_rol']) ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Datos del cliente ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Cliente</h2>
            <a href="../clientes/historial.php?id=<?= $venta['cliente_id'] ?>" class="btn btn--sm">Ver historial</a>
        </div>
        <div class="card__body">
            <div class="info-list">
                <div class="info-row">
                    <span class="info-row__label">Nombre</span>
                    <span class="info-row__valor"><strong><?= htmlspecialchars($venta['cliente']) ?></strong></span>
                </div>
                <?php if ($venta['cliente_tel']): ?>
                <div class="info-row">
                    <span class="info-row__label">Teléfono</span>
                    <span class="info-row__valor"><?= htmlspecialchars($venta['cliente_tel']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($venta['cliente_email']): ?>
                <div class="info-row">
                    <span class="info-row__label">Email</span>
                    <span class="info-row__valor"><?= htmlspecialchars($venta['cliente_email']) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($venta['cliente_dir']): ?>
                <div class="info-row">
                    <span class="info-row__label">Dirección</span>
                    <span class="info-row__valor"><?= htmlspecialchars($venta['cliente_dir']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Productos de la venta ── -->
    <div class="card" style="grid-column:1/-1">
        <div class="card__header">
            <h2 class="card__title">Productos</h2>
        </div>
        <div class="card__body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Precio unit.</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($d = mysqli_fetch_assoc($detalle)): ?>
                    <tr>
                        <td class="text-muted text-mono"><?= htmlspecialchars($d['codigo'] ?: '—') ?></td>
                        <td><strong><?= htmlspecialchars($d['producto']) ?></strong></td>
                        <td><?= $moneda ?> <?= number_format($d['precio_unitario'], 2) ?></td>
                        <td>
                            <span class="badge badge--gray"><?= $d['cantidad'] ?> uds.</span>
                        </td>
                        <td><strong><?= $moneda ?> <?= number_format($d['subtotal'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align:right; color:var(--text-muted,#6B7280)">Subtotal:</td>
                        <td><?= $moneda ?> <?= number_format($venta['subtotal'], 2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align:right; color:var(--text-muted,#6B7280)">IGV (18%):</td>
                        <td><?= $moneda ?> <?= number_format($venta['igv'], 2) ?></td>
                    </tr>
                    <tr style="font-size:1.1rem">
                        <td colspan="4" style="text-align:right"><strong>TOTAL:</strong></td>
                        <td><strong><?= $moneda ?> <?= number_format($venta['total'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<style>
.detalle-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.info-list    { display:flex; flex-direction:column; gap:0; }
.info-row {
    display:flex; justify-content:space-between; align-items:center;
    padding:10px 0; border-bottom:1px solid var(--border,#E0DED8);
    font-size:.9rem;
}
.info-row:last-child { border-bottom:none; }
.info-row__label { color:var(--text-muted,#6B7280); font-weight:500; }
.info-row__valor { font-weight:400; text-align:right; }
.text-mono { font-family:monospace; font-size:.85rem; }
@media(max-width:768px){ .detalle-grid { grid-template-columns:1fr; } }
</style>

<?php require_once '../../includes/footer.php'; ?>
