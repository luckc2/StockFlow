<?php
// ============================================================
//  StockFlow — Lista de ventas
//  Archivo: modules/ventas/index.php
// ============================================================
require_once '../../config/session.php';
require_once '../../config/database.php';

verificarSesion();

// ── Configuración de moneda ──────────────────────────────────
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Filtros ──────────────────────────────────────────────────
$desde  = $_GET['desde']  ?? date('Y-m-01');
$hasta  = $_GET['hasta']  ?? date('Y-m-d');
$estado = $_GET['estado'] ?? 'todos';
$buscar = trim($_GET['buscar'] ?? '');

$desde = date('Y-m-d', strtotime($desde));
$hasta = date('Y-m-d', strtotime($hasta));

$where = ["DATE(v.fecha) BETWEEN '$desde' AND '$hasta'"];
if ($estado !== 'todos') {
    $where[] = "v.estado = '" . mysqli_real_escape_string($conn, $estado) . "'";
}
if ($buscar) {
    $b       = mysqli_real_escape_string($conn, $buscar);
    $where[] = "(c.nombre LIKE '%$b%' OR u.nombre LIKE '%$b%' OR v.id LIKE '%$b%')";
}
$where_sql = implode(' AND ', $where);

// ── Paginación ───────────────────────────────────────────────
$por_pagina = 20;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS t
    FROM ventas v
    JOIN clientes c ON v.cliente_id = c.id
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE $where_sql
"))['t'];
$total_pags = ceil($total / $por_pagina);

// ── Ventas ───────────────────────────────────────────────────
$ventas = mysqli_query($conn, "
    SELECT
        v.id, v.fecha, v.subtotal, v.igv, v.total,
        v.estado, v.metodo_pago,
        c.nombre AS cliente,
        u.nombre AS vendedor,
        COUNT(dv.id) AS items
    FROM ventas v
    JOIN clientes c         ON v.cliente_id = c.id
    JOIN usuarios u         ON v.usuario_id = u.id
    LEFT JOIN detalle_venta dv ON dv.venta_id = v.id
    WHERE $where_sql
    GROUP BY v.id
    ORDER BY v.fecha DESC
    LIMIT $por_pagina OFFSET $offset
");

// ── Resumen del período ──────────────────────────────────────
$resumen = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_ventas,
        COALESCE(SUM(CASE WHEN v.estado='completada' THEN v.total ELSE 0 END), 0) AS monto_total,
        SUM(v.estado = 'anulada')   AS anuladas,
        SUM(v.estado = 'pendiente') AS pendientes
    FROM ventas v
    JOIN clientes c ON v.cliente_id = c.id
    JOIN usuarios u ON v.usuario_id = u.id
    WHERE $where_sql
"));

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Ventas</h1>
        <p class="page-subtitle">
            <?= date('d/m/Y', strtotime($desde)) ?> —
            <?= date('d/m/Y', strtotime($hasta)) ?>
        </p>
    </div>
    <?php if (tieneRol(['admin','vendedor'])): ?>
    <a href="nueva_venta.php" class="btn btn--primary1">+ Nueva venta</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert--success"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<!-- ── Resumen rápido ── -->
<div class="ventas-summary">
    <div class="ventas-summary__item">
        <span class="ventas-summary__num"><?= $resumen['total_ventas'] ?></span>
        <span class="ventas-summary__label">Total ventas</span>
    </div>
    <div class="ventas-summary__item ventas-summary__item--green">
        <span class="ventas-summary__num"><?= $moneda ?> <?= number_format($resumen['monto_total'], 2) ?></span>
        <span class="ventas-summary__label">Monto completadas</span>
    </div>
    <div class="ventas-summary__item ventas-summary__item--amber">
        <span class="ventas-summary__num"><?= $resumen['pendientes'] ?></span>
        <span class="ventas-summary__label">Pendientes</span>
    </div>
    <div class="ventas-summary__item ventas-summary__item--danger">
        <span class="ventas-summary__num"><?= $resumen['anuladas'] ?></span>
        <span class="ventas-summary__label">Anuladas</span>
    </div>
</div>

<!-- ── Filtros ── -->
<div class="card" style="margin-bottom:24px">
    <div class="card__body">
        <form method="GET" class="filter-form">
            <div class="form__group" style="flex:2">
                <label class="form__label">Buscar</label>
                <input type="text" name="buscar" class="form__input"
                       placeholder="Cliente, vendedor o número de venta..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
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
                    <option value="todos"      <?= $estado==='todos'      ? 'selected':'' ?>>Todos</option>
                    <option value="completada" <?= $estado==='completada' ? 'selected':'' ?>>Completada</option>
                    <option value="pendiente"  <?= $estado==='pendiente'  ? 'selected':'' ?>>Pendiente</option>
                    <option value="anulada"    <?= $estado==='anulada'    ? 'selected':'' ?>>Anulada</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-self:flex-end; margin-bottom:4px">
                <button type="submit" class="btn btn--primary">Buscar</button>
                <a href="index.php" class="btn btn--ghost">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Tabla ── -->
<div class="card">
    <div class="card__body p-0">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Vendedor</th>
                    <th>Ítems</th>
                    <th>Método</th>
                    <th>Subtotal</th>
                    <th>IGV</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($ventas) === 0): ?>
                <tr><td colspan="11" class="text-center text-muted" style="padding:32px">
                    No hay ventas en el período seleccionado.
                </td></tr>
            <?php else: ?>
            <?php while ($v = mysqli_fetch_assoc($ventas)): ?>
                <tr>
                    <td class="text-muted text-mono">#<?= str_pad($v['id'],4,'0',STR_PAD_LEFT) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                    <td><strong><?= htmlspecialchars($v['cliente']) ?></strong></td>
                    <td class="text-muted"><?= htmlspecialchars($v['vendedor']) ?></td>
                    <td>
                        <span class="badge badge--gray">
                            <?= $v['items'] ?> ítem<?= $v['items'] != 1 ? 's':'' ?>
                        </span>
                    </td>
                    <td><?= ucfirst($v['metodo_pago']) ?></td>
                    <td class="text-muted"><?= $moneda ?> <?= number_format($v['subtotal'],2) ?></td>
                    <td class="text-muted"><?= $moneda ?> <?= number_format($v['igv'],2) ?></td>
                    <td><strong><?= $moneda ?> <?= number_format($v['total'],2) ?></strong></td>
                    <td>
                        <span class="badge badge--<?= $v['estado'] ?>">
                            <?= ucfirst($v['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex">
                            <a href="detalle.php?id=<?= $v['id'] ?>" class="btn btn--sm m-1">Ver</a>
                            <a href="ticket.php?id=<?= $v['id'] ?>"
                            class="btn btn--sm btn--success-outline m-1"
                            target="_blank">🖨️</a>
                            <?php if ($v['estado'] === 'completada' && tieneRol(['admin'])): ?>
                            <a href="anular.php?id=<?= $v['id'] ?>"
                            class="btn btn--sm btn--danger-outline m-1"
                            data-confirm="¿Anular la venta #<?= $v['id'] ?>? Se restaurará el stock.">
                            Anular
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pags > 1): ?>
    <div class="card__footer pagination">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina-1 ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&estado=<?= $estado ?>&buscar=<?= urlencode($buscar) ?>" class="btn btn--sm">← Anterior</a>
        <?php endif; ?>
        <span class="pagination__info">Página <?= $pagina ?> de <?= $total_pags ?></span>
        <?php if ($pagina < $total_pags): ?>
            <a href="?pagina=<?= $pagina+1 ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&estado=<?= $estado ?>&buscar=<?= urlencode($buscar) ?>" class="btn btn--sm">Siguiente →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-form { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; }
.ventas-summary { display:flex; gap:16px; margin-bottom:24px; }
.ventas-summary__item {
    flex:1; background:var(--surface,#fff); border-radius:10px; padding:16px 20px;
    display:flex; flex-direction:column; gap:4px;
    border-left:4px solid var(--blue,#1F5FA5);
    box-shadow:var(--shadow,0 2px 8px rgba(0,0,0,.08));
}
.ventas-summary__item--green  { border-color:var(--green, #0F6E56); }
.ventas-summary__item--amber  { border-color:var(--amber, #B25A00); }
.ventas-summary__item--danger { border-color:var(--danger,#B91C1C); }
.ventas-summary__num   { font-size:1.5rem; font-weight:700; color:var(--white,#FFFFFF); }
.ventas-summary__label { font-size:.82rem; color:var(--text-muted,#6B7280); }
.text-mono    { font-family:monospace; font-size:.85rem; }
.text-center  { text-align:center; }
.pagination { display:flex; align-items:center; justify-content:center; gap:12px; padding:16px; border-top:1px solid var(--border,#E0DED8); }
.pagination__info { font-size:.85rem; color:var(--text-muted,#6B7280); }
@media(max-width:768px){ .ventas-summary { flex-direction:column; } }
</style>

<?php require_once '../../includes/footer.php'; ?>
