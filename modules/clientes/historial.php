<?php
// ============================================================
//  StockFlow — Historial de compras de un cliente
//  Archivo: modules/clientes/historial.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

$cliente = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM clientes WHERE id = $id"));
if (!$cliente) { header("Location: index.php"); exit(); }

// ── Configuración de moneda ──────────────────────────────────
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Estadísticas generales del cliente ──────────────────────
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                    AS total_compras,
        COALESCE(SUM(total),   0)   AS monto_total,
        COALESCE(MAX(total),   0)   AS compra_mayor,
        COALESCE(AVG(total),   0)   AS ticket_promedio,
        MAX(fecha)                  AS ultima_compra,
        MIN(fecha)                  AS primera_compra
    FROM ventas
    WHERE cliente_id = $id AND estado = 'completada'
"));

// ── Paginación ───────────────────────────────────────────────
$por_pagina = 10;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$total_ventas = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS t FROM ventas WHERE cliente_id = $id"
))['t'];
$total_pags = ceil($total_ventas / $por_pagina);

// ── Ventas del cliente ───────────────────────────────────────
$ventas = mysqli_query($conn, "
    SELECT
        v.id,
        v.fecha,
        v.subtotal,
        v.igv,
        v.total,
        v.estado,
        v.metodo_pago,
        u.nombre AS vendedor,
        COUNT(dv.id) AS cantidad_productos
    FROM ventas v
    JOIN usuarios u      ON v.usuario_id  = u.id
    LEFT JOIN detalle_venta dv ON dv.venta_id = v.id
    WHERE v.cliente_id = $id
    GROUP BY v.id
    ORDER BY v.fecha DESC
    LIMIT $por_pagina OFFSET $offset
");

// ── Producto más comprado por este cliente ───────────────────
$top_producto = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.nombre, SUM(dv.cantidad) AS veces
    FROM detalle_venta dv
    JOIN ventas   v ON dv.venta_id    = v.id
    JOIN productos p ON dv.producto_id = p.id
    WHERE v.cliente_id = $id AND v.estado = 'completada'
    GROUP BY p.id
    ORDER BY veces DESC
    LIMIT 1
"));

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Historial de compras</h1>
        <p class="page-subtitle"><?= htmlspecialchars($cliente['nombre']) ?></p>
    </div>
    <div style="display:flex; gap:8px">
        <a href="editar.php?id=<?= $id ?>" class="btn btn--ghost">Editar cliente</a>
        <a href="index.php" class="btn btn--ghost">← Volver</a>
    </div>
</div>

<!-- ── Datos del cliente ── -->
<div class="card" style="margin-bottom:24px">
    <div class="card__body">
        <div class="cliente-info">
            <div class="cliente-info__avatar">
                <?= strtoupper(substr($cliente['nombre'], 0, 1)) ?>
            </div>
            <div class="cliente-info__datos">
                <h2><?= htmlspecialchars($cliente['nombre']) ?></h2>
                <div class="cliente-info__detalle">
                    <?php if ($cliente['telefono']): ?>
                        <span>📞 <?= htmlspecialchars($cliente['telefono']) ?></span>
                    <?php endif; ?>
                    <?php if ($cliente['email']): ?>
                        <span>✉️ <?= htmlspecialchars($cliente['email']) ?></span>
                    <?php endif; ?>
                    <?php if ($cliente['direccion']): ?>
                        <span>📍 <?= htmlspecialchars($cliente['direccion']) ?></span>
                    <?php endif; ?>
                    <span>🗓️ Cliente desde <?= date('d/m/Y', strtotime($cliente['created_at'])) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── KPIs del cliente ── -->
<div class="kpi-grid" style="margin-bottom:24px">
    <div class="kpi-card kpi-card--blue">
        
        <span class="kpi-card__value"><?= $stats['total_compras'] ?></span>
        <span class="kpi-card__label">Compras completadas</span>
    </div>
    <div class="kpi-card kpi-card--green">
        
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($stats['monto_total'], 2) ?></span>
        <span class="kpi-card__label">Total gastado</span>
    </div>
    <div class="kpi-card kpi-card--purple">
        
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($stats['ticket_promedio'], 2) ?></span>
        <span class="kpi-card__label">Ticket promedio</span>
    </div>
    <div class="kpi-card kpi-card--amber">
        
        <span class="kpi-card__value">
            <?= $top_producto ? htmlspecialchars($top_producto['nombre']) : '—' ?>
        </span>
        <span class="kpi-card__label">Producto más comprado</span>
    </div>
</div>

<!-- ── Tabla de ventas ── -->
<div class="card">
    <div class="card__header">
        <h2 class="card__title">Compras realizadas</h2>
        <span class="badge badge--gray"><?= $total_ventas ?> en total</span>
    </div>
    <div class="card__body p-0">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Vendedor</th>
                    <th>Productos</th>
                    <th>Método pago</th>
                    <th>Subtotal</th>
                    <th>IGV</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($ventas) === 0): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:32px">
                    Este cliente no tiene compras registradas.
                </td></tr>
            <?php else: ?>
            <?php while ($v = mysqli_fetch_assoc($ventas)): ?>
                <tr>
                    <td class="text-muted"><?= $v['id'] ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($v['vendedor']) ?></td>
                    <td>
                        <span class="badge badge--gray">
                            <?= $v['cantidad_productos'] ?> ítem<?= $v['cantidad_productos'] != 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td><?= ucfirst($v['metodo_pago']) ?></td>
                    <td class="text-muted"><?= $moneda ?> <?= number_format($v['subtotal'], 2) ?></td>
                    <td class="text-muted"><?= $moneda ?> <?= number_format($v['igv'],      2) ?></td>
                    <td><strong><?= $moneda ?> <?= number_format($v['total'], 2) ?></strong></td>
                    <td>
                        <span class="badge badge--<?= $v['estado'] ?>">
                            <?= ucfirst($v['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <a href="../ventas/detalle.php?id=<?= $v['id'] ?>"
                           class="btn btn--sm">Ver</a>
                        <a href="../ventas/ticket.php?id=<?= $v['id'] ?>"
                           class="btn btn--sm" target="_blank">🖨️</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>

            <?php if ($stats['total_compras'] > 0): ?>
            <tfoot>
                <tr>
                    <td colspan="7"><strong>TOTAL GASTADO</strong></td>
                    <td><strong><?= $moneda ?> <?= number_format($stats['monto_total'], 2) ?></strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <!-- Paginación -->
    <?php if ($total_pags > 1): ?>
    <div class="card__footer pagination">
        <?php if ($pagina > 1): ?>
            <a href="?id=<?= $id ?>&pagina=<?= $pagina-1 ?>" class="btn btn--sm">← Anterior</a>
        <?php endif; ?>
        <span class="pagination__info">Página <?= $pagina ?> de <?= $total_pags ?></span>
        <?php if ($pagina < $total_pags): ?>
            <a href="?id=<?= $id ?>&pagina=<?= $pagina+1 ?>" class="btn btn--sm">Siguiente →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}
.kpi-card {
    background: var(--surface, #fff);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    border-left: 4px solid;
    box-shadow: var(--shadow, 0 2px 8px rgba(0,0,0,.08));
}
.kpi-card--blue   { border-color: var(--blue,   #1F5FA5); }
.kpi-card--green  { border-color: var(--green,  #0F6E56); }
.kpi-card--purple { border-color: var(--purple, #5344B7); }
.kpi-card--amber  { border-color: var(--amber,  #B25A00); }
.kpi-card__icon  { font-size: 1.4rem; }
.kpi-card__value { font-size: 1.3rem; font-weight: 700; line-height: 1.2; }
.kpi-card__label { font-size: .8rem; color: var(--text-muted, #6B7280); }

/* Info del cliente */
.cliente-info {
    display: flex;
    align-items: center;
    gap: 20px;
}
.cliente-info__avatar {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--blue, #1F5FA5);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    font-weight: 700;
    flex-shrink: 0;
}
.cliente-info__datos h2 {
    font-size: 1.2rem;
    margin-bottom: 6px;
}
.cliente-info__detalle {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: .85rem;
    color: var(--text-muted, #6B7280);
}

/* Paginación */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 16px;
    border-top: 1px solid var(--border, #E0DED8);
}
.pagination__info {
    font-size: .85rem;
    color: var(--text-muted, #6B7280);
}

.text-center { text-align: center; }

@media (max-width: 768px) {
    .kpi-grid           { grid-template-columns: 1fr 1fr; }
    .cliente-info       { flex-direction: column; text-align: center; }
    .cliente-info__detalle { justify-content: center; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
