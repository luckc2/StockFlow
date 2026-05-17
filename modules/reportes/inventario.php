<?php
// ============================================================
//  StockFlow — Reporte de Inventario
//  Archivo: modules/reportes/inventario.php
//  Solo admin
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

// ── Configuración ────────────────────────────────────────────
$config = [];
$rc = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
if ($rc) while ($row = mysqli_fetch_assoc($rc)) $config[$row['clave']] = $row['valor'];
$moneda = $config['moneda_simbolo'] ?? 'S/';

// ── Filtros ──────────────────────────────────────────────────
$filtro     = $_GET['filtro']     ?? 'todos';       // todos | stock_bajo | sin_stock
$categoria  = (int)($_GET['categoria'] ?? 0);

// ── Resumen general ──────────────────────────────────────────
$resumen = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)                                          AS total_productos,
        SUM(stock_actual)                                 AS total_unidades,
        SUM(stock_actual * precio_compra)                 AS valor_compra,
        SUM(stock_actual * precio_venta)                  AS valor_venta,
        SUM(stock_actual <= stock_minimo AND activo = 1)  AS stock_bajo,
        SUM(stock_actual = 0 AND activo = 1)              AS sin_stock
    FROM productos
    WHERE activo = 1
"));

// ── Consulta productos con filtros ───────────────────────────
$where = ["p.activo = 1"];
if ($filtro === 'stock_bajo') $where[] = "p.stock_actual <= p.stock_minimo AND p.stock_actual > 0";
if ($filtro === 'sin_stock')  $where[] = "p.stock_actual = 0";
if ($categoria > 0)           $where[] = "p.categoria_id = $categoria";

$where_sql = implode(' AND ', $where);

$productos = mysqli_query($conn, "
    SELECT
        p.id,
        p.codigo,
        p.nombre,
        p.stock_actual,
        p.stock_minimo,
        p.precio_compra,
        p.precio_venta,
        p.stock_actual * p.precio_compra  AS valor_compra,
        p.stock_actual * p.precio_venta   AS valor_venta,
        c.nombre  AS categoria,
        pr.nombre AS proveedor
    FROM productos p
    JOIN categorias  c  ON p.categoria_id  = c.id
    JOIN proveedores pr ON p.proveedor_id  = pr.id
    WHERE $where_sql
    ORDER BY p.stock_actual ASC, p.nombre ASC
");

// ── Categorías para el filtro ────────────────────────────────
$categorias = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre");

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Reporte de Inventario</h1>
        <p class="page-subtitle">Estado actual del stock — <?= date('d/m/Y H:i') ?></p>
    </div>
    <button onclick="window.print()" class="btn btn--ghost"> Imprimir reporte</button>
</div>

<!-- ── KPIs ── -->
<div class="kpi-grid" style="margin-bottom:24px">
    <div class="kpi-card kpi-card--blue">
        <span class="kpi-card__value"><?= $resumen['total_productos'] ?></span>
        <span class="kpi-card__label">Productos activos</span>
    </div>
    <div class="kpi-card kpi-card--green">
        <span class="kpi-card__value"><?= number_format($resumen['total_unidades']) ?></span>
        <span class="kpi-card__label">Unidades en stock</span>
    </div>
    <div class="kpi-card kpi-card--purple">
        <span class="kpi-card__value"><?= $moneda ?> <?= number_format($resumen['valor_venta'], 2) ?></span>
        <span class="kpi-card__label">Valor al precio de venta</span>
    </div>
    <div class="kpi-card kpi-card--amber">
        <span class="kpi-card__value"><?= $resumen['stock_bajo'] ?></span>
        <span class="kpi-card__label">Productos con stock bajo</span>
    </div>
</div>

<!-- ── Filtros ── -->
<div class="card no-print" style="margin-bottom:24px">
    <div class="card__body">
        <form method="GET" class="filter-form">
            <div class="form__group">
                <label class="form__label">Estado de stock</label>
                <select name="filtro" class="form__input">
                    <option value="todos"      <?= $filtro==='todos'      ? 'selected':'' ?>>Todos</option>
                    <option value="stock_bajo" <?= $filtro==='stock_bajo' ? 'selected':'' ?>>⚠️ Stock bajo</option>
                    <option value="sin_stock"  <?= $filtro==='sin_stock'  ? 'selected':'' ?>>🔴 Sin stock</option>
                </select>
            </div>
            <div class="form__group">
                <label class="form__label">Categoría</label>
                <select name="categoria" class="form__input">
                    <option value="0">Todas las categorías</option>
                    <?php while ($cat = mysqli_fetch_assoc($categorias)): ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria===$cat['id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-self:flex-end; margin-bottom:4px">
                <button type="submit" class="btn btn--primary">Filtrar</button>
                <a href="inventario.php" class="btn btn--ghost">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- ── Tabla principal ── -->
<div class="card">
    <div class="card__header">
        <h2 class="card__title">Stock actual de productos</h2>
        <span class="badge badge--gray"><?= mysqli_num_rows($productos) ?> productos</span>
    </div>
    <div class="card__body p-0">
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>P. Compra</th>
                    <th>P. Venta</th>
                    <th>Stock actual</th>
                    <th>Stock mínimo</th>
                    <th>Valor stock</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($productos) === 0): ?>
                <tr><td colspan="10" class="text-center text-muted" style="padding:32px">
                    No hay productos con ese filtro.
                </td></tr>
            <?php else: ?>
            <?php while ($p = mysqli_fetch_assoc($productos)): ?>
                <?php
                $sin_stock  = $p['stock_actual'] === 0;
                $bajo       = !$sin_stock && $p['stock_actual'] <= $p['stock_minimo'];
                $row_class  = $sin_stock ? 'row--danger' : ($bajo ? 'row--warning' : '');
                ?>
                <tr class="<?= $row_class ?>">
                    <td class="text-muted"><?= htmlspecialchars($p['codigo']) ?></td>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($p['categoria']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($p['proveedor']) ?></td>
                    <td><?= $moneda ?> <?= number_format($p['precio_compra'], 2) ?></td>
                    <td><?= $moneda ?> <?= number_format($p['precio_venta'],  2) ?></td>
                    <td>
                        <?php if ($sin_stock): ?>
                            <span class="badge badge--danger">0 — Sin stock</span>
                        <?php elseif ($bajo): ?>
                            <span class="badge badge--warning"><?= $p['stock_actual'] ?> ⚠️</span>
                        <?php else: ?>
                            <span class="badge badge--success"><?= $p['stock_actual'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $p['stock_minimo'] ?></td>
                    <td><?= $moneda ?> <?= number_format($p['valor_venta'], 2) ?></td>
                    <td>
                        <?php if ($sin_stock): ?>
                            <span class="badge badge--danger">Sin stock</span>
                        <?php elseif ($bajo): ?>
                            <span class="badge badge--warning">Stock bajo</span>
                        <?php else: ?>
                            <span class="badge badge--success">OK</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8"><strong>VALOR TOTAL DEL INVENTARIO</strong></td>
                    <td><strong><?= $moneda ?> <?= number_format($resumen['valor_venta'], 2) ?></strong></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<style>
.filter-form {
    display: grid;
    grid-template-columns: 1fr 1fr auto;
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

/* Filas con color según estado */
.row--danger  { background: rgba(185,28,28,.06); }
.row--warning { background: rgba(178,90,0,.06); }
.text-center  { text-align: center; }

@media print {
    .no-print    { display: none !important; }
    .topnav      { display: none !important; }
    .alert-stock { display: none !important; }
    body         { background: white; }
    .kpi-grid    { grid-template-columns: repeat(4,1fr); }
}

@media (max-width: 768px) {
    .kpi-grid    { grid-template-columns: 1fr 1fr; }
    .filter-form { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
