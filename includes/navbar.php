<?php
// includes/navbar.php — StockFlow

$badge_stock = 0;
if (tieneRol(['admin', 'almacen'])) {
    $r = mysqli_query($conn, "SELECT COUNT(*) AS t FROM productos WHERE stock_actual <= stock_minimo AND activo = 1");
    $badge_stock = (int)(mysqli_fetch_assoc($r)['t'] ?? 0);
}

$ruta = $_SERVER['REQUEST_URI'];

function esActivo($segmento) {
    global $ruta;
    return strpos($ruta, $segmento) !== false ? 'active' : '';
}
?>

<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">

        <a class="navbar-brand" href="/stockflow/dashboard.php">
            <span class="topnav__logo-icon">SF</span>
            <span class="topnav__logo-text">StockFlow</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarNavDropdown"
                aria-controls="navbarNavDropdown"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNavDropdown">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link <?= esActivo('dashboard') ?>"
                       href="/stockflow/dashboard.php"
                       <?= esActivo('dashboard') ? 'aria-current="page"' : '' ?>>
                        Dashboard
                    </a>
                </li>

                <?php if (tieneRol(['admin', 'vendedor', 'almacen'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= esActivo('/inventario/') ?>"
                       href="/stockflow/modules/inventario/index.php">
                        Inventario
                        <?php if ($badge_stock > 0): ?>
                            <span class="badge text-bg-danger"><?= $badge_stock ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>

                <?php if (tieneRol(['admin', 'vendedor'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= esActivo('/ventas/') ?>"
                       href="/stockflow/modules/ventas/index.php">
                        Ventas
                    </a>
                </li>
                <?php endif; ?>

                <?php if (tieneRol(['admin', 'vendedor'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= esActivo('/clientes/') ?>"
                       href="/stockflow/modules/clientes/index.php">
                        Clientes
                    </a>
                </li>
                <?php endif; ?>

                <?php if (tieneRol(['admin'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle"
                       href="#"
                       role="button"
                       data-bs-toggle="dropdown"
                       aria-expanded="false">
                        Reportes
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item"
                               href="/stockflow/modules/reportes/ventas.php">
                                Ventas
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item"
                               href="/stockflow/modules/reportes/inventario.php">
                                Inventario
                            </a>
                        </li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (tieneRol(['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= esActivo('/admin/u') ?>"
                       href="/stockflow/modules/admin/usuarios.php">
                        Admin
                    </a>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link <?= esActivo('/admin/configuracion') ?>"
                       href="/stockflow/modules/admin/configuracion.php">
                        Configuración
                    </a>
                </li>

            </ul>

            <div class="d-flex align-items-center gap-3">
                <span class="topnav__role"><?= htmlspecialchars($_SESSION['usuario_rol']) ?></span>
                <span class="topnav__name"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
                <a href="/stockflow/modules/auth/logout.php"
                   class="topnav__logout"
                   data-confirm="¿Cerrar sesión?">
                    Logout
                </a>
            </div>

        </div>
    </div>
</nav>

<?php if ($badge_stock > 0 && tieneRol(['admin', 'almacen'])): ?>
<div class="alert-stock">
    ⚠️ <strong><?= $badge_stock ?></strong> producto<?= $badge_stock > 1 ? 's' : '' ?> con stock bajo.
    <a href="/stockflow/modules/inventario/index.php?filtro=stock_bajo">Ver productos →</a>
</div>
<?php endif; ?>
