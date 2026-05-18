<?php
// ============================================================
//  StockFlow — Lista de productos / Inventario
//  Archivo: modules/inventario/index.php
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
$buscar    = trim($_GET['buscar']    ?? '');
$categoria = (int)($_GET['categoria'] ?? 0);
$filtro    = $_GET['filtro']          ?? 'todos';

$where = ["p.activo = 1"];
if ($buscar) {
    $b       = mysqli_real_escape_string($conn, $buscar);
    $where[] = "(p.nombre LIKE '%$b%' OR p.codigo LIKE '%$b%')";
}
if ($categoria > 0) $where[] = "p.categoria_id = $categoria";
if ($filtro === 'stock_bajo') $where[] = "p.stock_actual <= p.stock_minimo AND p.stock_actual > 0";
if ($filtro === 'sin_stock')  $where[] = "p.stock_actual = 0";
$where_sql = implode(' AND ', $where);

// ── Paginación ───────────────────────────────────────────────
$por_pagina = 20;
$pagina     = max(1, (int)($_GET['pagina'] ?? 1));
$offset     = ($pagina - 1) * $por_pagina;

$total = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS t
    FROM productos p
    JOIN categorias c   ON p.categoria_id = c.id
    JOIN proveedores pr ON p.proveedor_id = pr.id
    WHERE $where_sql
"))['t'];
$total_pags = ceil($total / $por_pagina);

$productos = mysqli_query($conn, "
    SELECT p.*, c.nombre AS categoria, pr.nombre AS proveedor
    FROM productos p
    JOIN categorias  c  ON p.categoria_id = c.id
    JOIN proveedores pr ON p.proveedor_id = pr.id
    WHERE $where_sql
    ORDER BY p.nombre ASC
    LIMIT $por_pagina OFFSET $offset
");

$categorias = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre");

$resumen = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total,
        SUM(stock_actual = 0 AND activo = 1) AS sin_stock,
        SUM(stock_actual <= stock_minimo AND stock_actual > 0 AND activo = 1) AS stock_bajo
    FROM productos WHERE activo = 1
"));

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Inventario</h1>
        <p class="page-subtitle"><?= $total ?> producto<?= $total != 1 ? 's' : '' ?> encontrado<?= $total != 1 ? 's' : '' ?></p>
    </div>
    <?php if (tieneRol(['admin', 'almacen'])): ?>
    <a href="crear.php" class="btn btn--primary1">+ Nuevo producto</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert--success"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<!-- ── Resumen rápido ── -->
<div class="inv-summary">
    <a href="index.php" class="inv-summary__item <?= $filtro==='todos' ? 'active':'' ?>">
        <span class="inv-summary__num"><?= $resumen['total'] ?></span>
        <span class="inv-summary__label">Total productos</span>
    </a>
    <a href="?filtro=stock_bajo" class="inv-summary__item inv-summary__item--warning <?= $filtro==='stock_bajo' ? 'active':'' ?>">
        <span class="inv-summary__num"><?= $resumen['stock_bajo'] ?></span>
        <span class="inv-summary__label">⚠️ Stock bajo</span>
    </a>
    <a href="?filtro=sin_stock" class="inv-summary__item inv-summary__item--danger <?= $filtro==='sin_stock' ? 'active':'' ?>">
        <span class="inv-summary__num"><?= $resumen['sin_stock'] ?></span>
        <span class="inv-summary__label">🔴 Sin stock</span>
    </a>
</div>

<!-- ── Filtros ── -->
<div class="card" style="margin-bottom:24px">
    <div class="card__body">
        <form method="GET" class="filter-form">
            <div class="form__group" style="flex:2">
                <label class="form__label">Buscar</label>
                <input type="text" name="buscar" class="form__input"
                       placeholder="Nombre o código del producto..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="form__group" style="flex:1">
                <label class="form__label">Categoría</label>
                <select name="categoria" class="form__input">
                    <option value="0">Todas</option>
                    <?php
                    mysqli_data_seek($categorias, 0);
                    while ($cat = mysqli_fetch_assoc($categorias)):
                    ?>
                    <option value="<?= $cat['id'] ?>" <?= $categoria===$cat['id'] ? 'selected':'' ?>>
                        <?= htmlspecialchars($cat['nombre']) ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form__group" style="flex:1">
                <label class="form__label">Stock</label>
                <select name="filtro" class="form__input">
                    <option value="todos"      <?= $filtro==='todos'      ? 'selected':'' ?>>Todos</option>
                    <option value="stock_bajo" <?= $filtro==='stock_bajo' ? 'selected':'' ?>>Stock bajo</option>
                    <option value="sin_stock"  <?= $filtro==='sin_stock'  ? 'selected':'' ?>>Sin stock</option>
                </select>
            </div>
            <div style="display:flex; gap:8px; align-self:flex-end; margin-bottom:4px">
                <button type="submit" class="btn btn--primary">Buscar</button>
                <?php if ($buscar || $categoria || $filtro !== 'todos'): ?>
                    <a href="index.php" class="btn btn--ghost">Limpiar</a>
                <?php endif; ?>
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
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Proveedor</th>
                    <th>P. Compra</th>
                    <th>P. Venta</th>
                    <th>Stock</th>
                    <th>Mín.</th>
                    <th>Estado</th>
                    <?php if (tieneRol(['admin', 'almacen'])): ?>
                    <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($productos) === 0): ?>
                <tr>
                    <td colspan="10" class="text-center text-muted" style="padding:32px">
                        No se encontraron productos con ese filtro.
                    </td>
                </tr>
            <?php else: ?>
            <?php while ($p = mysqli_fetch_assoc($productos)):
                $sin_stock = (int)$p['stock_actual'] === 0;
                $bajo      = !$sin_stock && $p['stock_actual'] <= $p['stock_minimo'];
                $row_class = $sin_stock ? 'row--danger' : ($bajo ? 'row--warning' : '');
            ?>
                <tr class="<?= $row_class ?>">
                    <td class="text-muted text-mono"><?= htmlspecialchars($p['codigo'] ?: '—') ?></td>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($p['categoria']) ?></td>
                    <td class="text-muted"><?= htmlspecialchars($p['proveedor']) ?></td>
                    <td><?= $moneda ?> <?= number_format($p['precio_compra'], 2) ?></td>
                    <td><?= $moneda ?> <?= number_format($p['precio_venta'],  2) ?></td>
                    <td>
                        <?php if ($sin_stock): ?>
                            <span class="badge badge--danger">0</span>
                        <?php elseif ($bajo): ?>
                            <span class="badge badge--warning"><?= $p['stock_actual'] ?></span>
                        <?php else: ?>
                            <span class="badge badge--success"><?= $p['stock_actual'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= $p['stock_minimo'] ?></td>
                    <td>
                        <?php if ($sin_stock): ?>
                            <span class="badge badge--danger">Sin stock</span>
                        <?php elseif ($bajo): ?>
                            <span class="badge badge--warning">Stock bajo</span>
                        <?php else: ?>
                            <span class="badge badge--success">OK</span>
                        <?php endif; ?>
                    </td>
                    <?php if (tieneRol(['admin', 'almacen'])): ?>
                    <td>
                        <div class="d-flex">
                            <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn--sm btn--warning m-1">Editar</a>
                            <a href="eliminar.php?id=<?= $p['id'] ?>"
                            class="btn btn--sm btn--danger-outline m-1"
                            data-confirm="¿Eliminar «<?= htmlspecialchars($p['nombre']) ?>»?">
                            Eliminar
                            </a>
                        </div>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pags > 1): ?>
    <div class="card__footer pagination">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina-1 ?>&buscar=<?= urlencode($buscar) ?>&categoria=<?= $categoria ?>&filtro=<?= $filtro ?>" class="btn btn--sm">← Anterior</a>
        <?php endif; ?>
        <span class="pagination__info">Página <?= $pagina ?> de <?= $total_pags ?></span>
        <?php if ($pagina < $total_pags): ?>
            <a href="?pagina=<?= $pagina+1 ?>&buscar=<?= urlencode($buscar) ?>&categoria=<?= $categoria ?>&filtro=<?= $filtro ?>" class="btn btn--sm">Siguiente →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-form { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; }
.inv-summary { display:flex; gap:16px; margin-bottom:24px; }
.inv-summary__item {
    flex:1; background:var(--surface,#fff); border-radius:10px; padding:16px 20px;
    text-decoration:none; display:flex; flex-direction:column; gap:4px;
    border:2px solid transparent; box-shadow:var(--shadow,0 2px 8px rgba(0,0,0,.08));
    transition:border-color .2s;
}
.inv-summary__item:hover, .inv-summary__item.active          { border-color:var(--blue,#1F5FA5); }
.inv-summary__item--warning:hover,.inv-summary__item--warning.active { border-color:var(--amber,#B25A00); }
.inv-summary__item--danger:hover, .inv-summary__item--danger.active  { border-color:var(--danger,#B91C1C); }
.inv-summary__num   { font-size:1.8rem; font-weight:700; color:var(--white,#FFFFFF); }
.inv-summary__label { font-size:.82rem; color:var(--text-muted,#6B7280); }
.row--danger  { background:rgba(185,28,28,.05); }
.row--warning { background:rgba(178,90,0,.05); }
.text-mono    { font-family:monospace; font-size:.85rem; }
.text-center  { text-align:center; }
.pagination { display:flex; align-items:center; justify-content:center; gap:12px; padding:16px; border-top:1px solid var(--border,#E0DED8); }
.pagination__info { font-size:.85rem; color:var(--text-muted,#6B7280); }
@media(max-width:768px){ .inv-summary{ flex-direction:column; } }
</style>

<?php require_once '../../includes/footer.php'; ?>
