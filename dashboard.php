<?php
// dashboard.php — StockFlow (raíz del proyecto)
require_once 'config/session.php';
require_once 'config/database.php';
verificarSesion();

$pageTitle = 'Dashboard — StockFlow';

// Ventas de hoy  →  tu BD usa 'fecha', no 'creado_en'
$res = mysqli_query($conn,
    "SELECT COUNT(*) AS total_ventas, COALESCE(SUM(total),0) AS ingresos
     FROM ventas WHERE DATE(fecha) = CURDATE() AND estado = 'completada'"
);
$hoy = mysqli_fetch_assoc($res);

// Total productos activos
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM productos WHERE activo = 1");
$totalProductos = mysqli_fetch_assoc($res)['total'];

// Productos con stock bajo  →  usamos tu vista v_stock_bajo
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM v_stock_bajo");
$stockBajo = mysqli_fetch_assoc($res)['total'];

// Total clientes
$res = mysqli_query($conn, "SELECT COUNT(*) AS total FROM clientes");
$totalClientes = mysqli_fetch_assoc($res)['total'];

// Últimas 6 ventas  →  'fecha' en vez de 'creado_en', id en vez de codigo
$ultimasVentas = mysqli_query($conn,
    "SELECT v.id, v.total, v.fecha, v.estado,
            c.nombre AS cliente, u.nombre AS vendedor
     FROM ventas v
     JOIN clientes c ON c.id = v.cliente_id
     JOIN usuarios u ON u.id = v.usuario_id
     ORDER BY v.fecha DESC LIMIT 6"
);

// Top 5 productos más vendidos (mes actual)  →  'detalle_venta' y 'fecha'
$topProductos = mysqli_query($conn,
    "SELECT p.nombre, SUM(dv.cantidad) AS unidades
     FROM detalle_venta dv
     JOIN productos p ON p.id = dv.producto_id
     JOIN ventas v ON v.id = dv.venta_id
     WHERE MONTH(v.fecha) = MONTH(CURDATE())
       AND YEAR(v.fecha)  = YEAR(CURDATE())
       AND v.estado = 'completada'
     GROUP BY dv.producto_id
     ORDER BY unidades DESC LIMIT 5"
);

// Ventas últimos 7 días (para mini gráfico)  →  'fecha'
$ventas7dias = mysqli_query($conn,
    "SELECT DATE(fecha) AS fecha, COALESCE(SUM(total),0) AS total
     FROM ventas
     WHERE fecha >= CURDATE() - INTERVAL 6 DAY AND estado = 'completada'
     GROUP BY DATE(fecha)
     ORDER BY fecha ASC"
);
$graficoData = [];
while ($r = mysqli_fetch_assoc($ventas7dias)) {
    $graficoData[] = $r;
}

require_once 'includes/header.php';
?>

<div class="dash">
    <!-- ── KPI Cards ── -->
    <section class="kpi-grid">
        <div class="kpi-card">
            <div>
                <p class="kpi-label">Ingresos hoy</p>
                <p class="kpi-value">S/ <?= number_format($hoy['ingresos'], 2) ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <p class="kpi-label">Ventas hoy</p>
                <p class="kpi-value"><?= $hoy['total_ventas'] ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <p class="kpi-label">Productos activos</p>
                <p class="kpi-value"><?= $totalProductos ?></p>
            </div>
        </div>
        <div class="kpi-card <?= $stockBajo > 0 ? 'kpi-card--warn' : '' ?>">
            <div>
                <p class="kpi-label">Stock bajo</p>
                <p class="kpi-value"><?= $stockBajo ?></p>
            </div>
        </div>
        <div class="kpi-card">
            <div>
                <p class="kpi-label">Clientes</p>
                <p class="kpi-value"><?= $totalClientes ?></p>
            </div>
        </div>
    </section>

    <div class="dash-cols">
        <!-- ── Últimas ventas ── -->
        <section class="card">
            <div class="card-head">
                <h2 class="card-title">Últimas ventas</h2>
                <a href="/stockflow/modules/ventas/index.php" class="card-link">Ver todas →</a>
            </div>
            <table class="sf-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($v = mysqli_fetch_assoc($ultimasVentas)): ?>
                    <tr>
                        <!-- Tu BD no tiene columna 'codigo' en ventas, lo generamos del id -->
                        <td <code>VTA-<?= str_pad($v['id'], 5, '0', STR_PAD_LEFT) ?></code></td>
                        <td><?= htmlspecialchars($v['cliente']) ?></td>
                        <td><?= htmlspecialchars($v['vendedor']) ?></td>
                        <td>S/ <?= number_format($v['total'], 2) ?></td>
                        <td>
                            <span class="badge badge--<?= $v['estado'] === 'completada' ? 'ok' : 'danger' ?>">
                                <?= $v['estado'] ?>
                            </span>
                        </td>
                        <td><?= date('d/m/y H:i', strtotime($v['fecha'])) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <!-- ── Columna derecha ── -->
        <aside class="dash-side">
            <!-- Mini gráfico 7 días -->
            <section class="card">
                <div class="card-head">
                    <h2 class="card-title">Ventas — últimos 7 días</h2>
                </div>
                <canvas id="chartVentas" height="160"></canvas>
            </section>

            <!-- Top productos -->
            <section class="card">
                <div class="card-head">
                    <h2 class="card-title">Top productos (mes)</h2>
                </div>
                <ul class="top-list">
                <?php
                $pos = 1;
                while ($p = mysqli_fetch_assoc($topProductos)):
                ?>
                    <li class="top-list__item">
                        <span class="top-list__pos"><?= $pos++ ?></span>
                        <span class="top-list__name"><?= htmlspecialchars($p['nombre']) ?></span>
                        <span class="top-list__val"><?= $p['unidades'] ?> uds</span>
                    </li>
                <?php endwhile; ?>
                </ul>
            </section>
        </aside>
    </div>
</div>

<!-- Chart.js mini gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const raw    = <?= json_encode($graficoData) ?>;
    const labels = raw.map(r => {
        const d = new Date(r.fecha + 'T00:00:00');
        return d.toLocaleDateString('es-PE', { weekday: 'short', day: 'numeric' });
    });
    const data = raw.map(r => parseFloat(r.total));

    new Chart(document.getElementById('chartVentas'), {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'S/ ingresos',
                data,
                backgroundColor: 'rgba(79,142,247,.7)',
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#7a7f96' } },
                y: { grid: { color: 'rgba(255,255,255,.05)' }, ticks: { color: '#7a7f96' } }
            }
        }
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
