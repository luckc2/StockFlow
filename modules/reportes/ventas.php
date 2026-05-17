<?php
// ============================================================
//  StockFlow — Reporte de Ventas
//  Archivo: modules/reportes/ventas.php
//  Solo admin
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

// ── Configuración de la empresa (para el encabezado del reporte)
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Filtros de fecha ─────────────────────────────────────────
$desde = $_GET['desde'] ?? date('Y-m-01');         // primer día del mes
$hasta = $_GET['hasta'] ?? date('Y-m-d');           // hoy
$estado_filtro = $_GET['estado'] ?? 'completada';

// Sanitizar fechas
$desde = date('Y-m-d', strtotime($desde));
$hasta = date('Y-m-d', strtotime($hasta));

// ── Consulta principal ───────────────────────────────────────
$sql = "
    SELECT
        v.id,
        v.fecha,
        v.subtotal,
        v.igv,
        v.total,
        v.estado,
        v.metodo_pago,
        c.nombre  AS cliente,
        u.nombre  AS vendedor
    FROM ventas v
    JOIN clientes c ON v.cliente_id  = c.id
    JOIN usuarios u ON v.usuario_id  = u.id
    WHERE DATE(v.fecha) BETWEEN '$desde' AND '$hasta'
";
if ($estado_filtro !== 'todos') {
    $sql .= " AND v.estado = '" . mysqli_real_escape_string($conn, $estado_filtro) . "'";
}
$sql .= " ORDER BY v.fecha DESC";
$ventas = mysqli_query($conn, $sql);

// ── Resumen del período ──────────────────────────────────────
$resumen = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                          AS total_ventas,
        COALESCE(SUM(total),    0)        AS total_monto,
        COALESCE(SUM(igv),      0)        AS total_igv,
        COALESCE(SUM(subtotal), 0)        AS total_subtotal,
        COALESCE(AVG(total),    0)        AS promedio_venta
    FROM ventas
    WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'
      AND estado = 'completada'
"));

// ── Top 5 productos del período ──────────────────────────────
$top_productos = mysqli_query($conn, "
    SELECT
        p.nombre,
        SUM(dv.cantidad)  AS unidades,
        SUM(dv.subtotal)  AS total
    FROM detalle_venta dv
    JOIN productos p     ON dv.producto_id = p.id
    JOIN ventas v        ON dv.venta_id    = v.id
    WHERE DATE(v.fecha) BETWEEN '$desde' AND '$hasta'
      AND v.estado = 'completada'
    GROUP BY p.id, p.nombre
    ORDER BY total DESC
    LIMIT 5
");

// ── Ventas por método de pago ────────────────────────────────
$por_metodo = mysqli_query($conn, "
    SELECT
        metodo_pago,
        COUNT(*)       AS cantidad,
        SUM(total)     AS monto
    FROM ventas
    WHERE DATE(fecha) BETWEEN '$desde' AND '$hasta'
      AND estado = 'completada'
    GROUP BY metodo_pago
    ORDER BY monto DESC
");

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reporte de Ventas</h1>
        <p class="page-subtitle">
            <?= date('d/m/Y', strtotime($desde)) ?> —
            <?= date('d/m/Y', strtotime($hasta)) ?>
        </p>
    </div>
    <button onclick="window.print()" class="btn btn--ghost">Imprimir reporte</button>
</div>

<!-- ── Filtros ── -->
<div class="card no-print" style="margin-bottom:24px">
    <div class="card__body">
        <form method="GET" class="filter-form">
            <div class="form__group">
                <label class="form__label">Desde</label>
                <input type="date" name="desde" class="form__input" value="<?= $desde ?>">
            </div>
            <div class="form__group">
                <label class="form__label">Hasta</label>
                <input type="date" name="hasta" class="form__input" value="<?= $hasta ?>">
            </div>
            <div class="form__group">
                <label class="form__label">Estado</label>
                <select name="estado" class="form__input">
                    <option value="completada" <?= $estado_filtro==='completada' ? 'selected':'' ?>>Completadas</option>
                    <option value="pendiente"  <?= $estado_filtro==='pendiente'  ? 'selected':'' ?>>Pendientes</option>
                    <option value="anulada"    <?= $estado_filtro==='anulada'    ? 'selected':'' ?>>Anuladas</option>
                    <option value="todos"      <?= $estado_filtro==='todos'      ? 'selected':'' ?>>Todos</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-self:flex-end; margin-bottom:4px">
                <button type="submit" class="btn btn--primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ── KPIs del período ── -->
<div class="kpi-grid" style="margin-bottom:24px">
    <div class="kpi-card kpi-card--blue">
        <span class="kpi-card__value"><?= $resumen['total_ventas'] ?></span>
        <span class="kpi-card__label">Ventas completadas</span>
    </div>
    <div class="kpi-card kpi-card--green">
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($resumen['total_monto'], 2) ?></span>
        <span class="kpi-card__label">Total recaudado</span>
    </div>
    <div class="kpi-card kpi-card--purple">
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($resumen['promedio_venta'], 2) ?></span>
        <span class="kpi-card__label">Ticket promedio</span>
    </div>
    <div class="kpi-card kpi-card--amber">
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($resumen['total_igv'], 2) ?></span>
        <span class="kpi-card__label">IGV total</span>
    </div>
</div>

<div class="reportes-grid">

    <!-- ── Tabla de ventas ── -->
    <div class="card" style="grid-column: 1/-1">
        <div class="card__header">
            <h2 class="card__title">Detalle de ventas</h2>
            <span class="badge badge--gray"><?= mysqli_num_rows($ventas) ?> registros</span>
        </div>
        <div class="card__body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Método</th>
                        <th>Subtotal</th>
                        <th>IGV</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="no-print">Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($ventas) === 0): ?>
                    <tr><td colspan="10" class="text-center text-muted" style="padding:32px">
                        No hay ventas en el período seleccionado.
                    </td></tr>
                <?php else: ?>
                <?php while ($v = mysqli_fetch_assoc($ventas)): ?>
                    <tr>
                        <td class="text-muted"><?= $v['id'] ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                        <td class="text-muted"><?= htmlspecialchars($v['vendedor']) ?></td>
                        <td><?= ucfirst($v['metodo_pago']) ?></td>
                        <td><?= $moneda ?> <?= number_format($v['subtotal'], 2) ?></td>
                        <td class="text-muted"><?= $moneda ?> <?= number_format($v['igv'], 2) ?></td>
                        <td><strong><?= $moneda ?> <?= number_format($v['total'], 2) ?></strong></td>
                        <td><span class="badge badge--<?= $v['estado'] ?>"><?= ucfirst($v['estado']) ?></span></td>
                        <td class="no-print">
                            <a href="../ventas/detalle.php?id=<?= $v['id'] ?>" class="btn btn--sm">Ver</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5"><strong>TOTALES</strong></td>
                        <td><strong><?= $moneda ?> <?= number_format($resumen['total_subtotal'], 2) ?></strong></td>
                        <td><strong><?= $moneda ?> <?= number_format($resumen['total_igv'], 2) ?></strong></td>
                        <td><strong><?= $moneda ?> <?= number_format($resumen['total_monto'], 2) ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ── Top productos ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Top 5 productos</h2>
            <span class="badge badge--gray">por monto vendido</span>
        </div>
        <div class="card__body p-0">
            <table class="table">
                <thead>
                    <tr><th>Producto</th><th>Unidades</th><th>Total</th></tr>
                </thead>
                <tbody>
                <?php
                $rank = 1;
                while ($p = mysqli_fetch_assoc($top_productos)):
                ?>
                    <tr>
                        <td>
                            <span class="rank-badge"><?= $rank++ ?></span>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </td>
                        <td><?= $p['unidades'] ?> uds.</td>
                        <td><strong><?= $moneda ?> <?= number_format($p['total'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Por método de pago ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Por método de pago</h2>
        </div>
        <div class="card__body p-0">
            <table class="table">
                <thead>
                    <tr><th>Método</th><th>Cantidad</th><th>Monto</th></tr>
                </thead>
                <tbody>
                <?php while ($m = mysqli_fetch_assoc($por_metodo)): ?>
                    <tr>
                        <td><?= ucfirst($m['metodo_pago']) ?></td>
                        <td><?= $m['cantidad'] ?> ventas</td>
                        <td><strong><?= $moneda ?> <?= number_format($m['monto'], 2) ?></strong></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- .reportes-grid -->

<style>
.filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr auto;
    gap: 16px;
    align-items: flex-end;
}
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
.kpi-card__value { font-size: 1.5rem; font-weight: 700; }
.kpi-card__label { font-size: .8rem; color: var(--text-muted, #6B7280); }
.reportes-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}
.rank-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px; height: 20px;
    background: var(--blue-l, #E6F1FB);
    color: var(--blue, #1F5FA5);
    border-radius: 50%;
    font-size: .75rem;
    font-weight: 700;
    margin-right: 6px;
}
.text-center { text-align: center; }

/* Estilos para impresión */
@media print {
    .no-print  { display: none !important; }
    .topnav    { display: none !important; }
    .alert-stock { display: none !important; }
    body       { background: white; }
    .kpi-grid  { grid-template-columns: repeat(4,1fr); }
    .reportes-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 768px) {
    .filter-form   { grid-template-columns: 1fr 1fr; }
    .kpi-grid      { grid-template-columns: 1fr 1fr; }
    .reportes-grid { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
