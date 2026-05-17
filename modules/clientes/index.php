<?php
// ============================================================
//  StockFlow — Lista de clientes
//  Archivo: modules/clientes/index.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

// ── Buscador ─────────────────────────────────────────────────
$buscar = trim($_GET['buscar'] ?? '');
$where  = '';
if ($buscar) {
    $b     = mysqli_real_escape_string($conn, $buscar);
    $where = "WHERE nombre LIKE '%$b%' OR telefono LIKE '%$b%' OR email LIKE '%$b%'";
}

// ── Paginación ───────────────────────────────────────────────
$por_pagina  = 15;
$pagina      = max(1, (int)($_GET['pagina'] ?? 1));
$offset      = ($pagina - 1) * $por_pagina;

$total       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM clientes $where"))['t'];
$total_pags  = ceil($total / $por_pagina);

$clientes = mysqli_query($conn, "
    SELECT c.*,
           COUNT(v.id)        AS total_compras,
           COALESCE(SUM(v.total), 0) AS monto_total
    FROM clientes c
    LEFT JOIN ventas v ON v.cliente_id = c.id AND v.estado = 'completada'
    $where
    GROUP BY c.id
    ORDER BY c.nombre ASC
    LIMIT $por_pagina OFFSET $offset
");

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Clientes</h1>
        <p class="page-subtitle"><?= $total ?> cliente<?= $total !== 1 ? 's' : '' ?> registrado<?= $total !== 1 ? 's' : '' ?></p>
    </div>
    <?php if (tieneRol(['admin', 'vendedor'])): ?>
    <a href="crear.php" class="btn btn--primary1">+ Nuevo cliente</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert--success"><?= htmlspecialchars($_GET['msg']) ?></div>
<?php endif; ?>

<!-- ── Buscador ── -->
<div class="card no-print" style="margin-bottom:24px">
    <div class="card__body">
        <form method="GET" class="filter-form">
            <div class="form__group" style="flex:1">
                <label class="form__label">Buscar cliente</label>
                <input type="text" name="buscar" class="form__input"
                       placeholder="Nombre, teléfono o email..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div style="display:flex; gap:8px; align-self:flex-end; margin-bottom:4px">
                <button type="submit" class="btn btn--primary">Buscar</button>
                <?php if ($buscar): ?>
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
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Email</th>
                    <th>Dirección</th>
                    <th>Compras</th>
                    <th>Total gastado</th>
                    <th>Registrado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (mysqli_num_rows($clientes) === 0): ?>
                <tr><td colspan="9" class="text-center text-muted" style="padding:32px">
                    <?= $buscar ? "No se encontraron clientes con \"$buscar\"." : 'No hay clientes registrados aún.' ?>
                </td></tr>
            <?php else: ?>
            <?php while ($c = mysqli_fetch_assoc($clientes)): ?>
                <tr>
                    <td class="text-muted"><?= $c['id'] ?></td>
                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars($c['telefono'] ?: '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($c['email'] ?: '—') ?></td>
                    <td class="text-muted"><?= htmlspecialchars($c['direccion'] ?: '—') ?></td>
                    <td>
                        <span class="badge badge--blue"><?= $c['total_compras'] ?> compra<?= $c['total_compras'] != 1 ? 's' : '' ?></span>
                    </td>
                    <td><strong>S/ <?= number_format($c['monto_total'], 2) ?></strong></td>
                    <td class="text-muted"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                    <td>
                        <div class="d-flex">
                            <a href="editar.php?id=<?= $c['id'] ?>"
                            class="btn btn--sm btn--warning">Editar</a>
                            <a href="historial.php?id=<?= $c['id'] ?>"
                            class="btn btn--sm ">Historial</a>
                        </div>
                        
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ── Paginación ── -->
    <?php if ($total_pags > 1): ?>
    <div class="card__footer pagination">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?= $pagina-1 ?>&buscar=<?= urlencode($buscar) ?>" class="btn btn--sm">← Anterior</a>
        <?php endif; ?>
        <span class="pagination__info">Página <?= $pagina ?> de <?= $total_pags ?></span>
        <?php if ($pagina < $total_pags): ?>
            <a href="?pagina=<?= $pagina+1 ?>&buscar=<?= urlencode($buscar) ?>" class="btn btn--sm">Siguiente →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<style>
.filter-form {
    display: flex;
    gap: 16px;
    align-items: flex-end;
}
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
</style>

<?php require_once '../../includes/footer.php'; ?>
