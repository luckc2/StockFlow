<?php
// modules/ventas/nueva_venta.php — StockFlow
require_once '../../config/session.php';
require_once '../../config/database.php';
verificarSesion();
if (!tieneRol(['admin', 'vendedor'])) {
    header('Location: /stockflow/dashboard.php');
    exit();
}

$pageTitle = 'Nueva Venta — StockFlow';
$error     = '';
$exito     = '';

// ── Procesar POST (registrar venta) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id  = (int)($_POST['cliente_id']  ?? 0);
    $metodo_pago = trim($_POST['metodo_pago']  ?? 'efectivo');
    $tipo_comp   = trim($_POST['tipo_comprobante'] ?? 'ticket');
    $productos   = $_POST['productos']         ?? [];  // array de {id, cantidad}

    // Validaciones básicas
    if ($cliente_id <= 0) {
        $error = 'Selecciona un cliente.';
    } elseif (empty($productos)) {
        $error = 'Agrega al menos un producto.';
    } else {
        // Iniciar transacción
        mysqli_begin_transaction($conn);
        try {
            $subtotal = 0.00;
            $lineas   = [];

            // Verificar stock y calcular totales
            foreach ($productos as $item) {
                $pid = (int)$item['id'];
                $qty = (int)$item['cantidad'];
                if ($pid <= 0 || $qty <= 0) continue;

                $stmt = mysqli_prepare($conn,
                    "SELECT nombre, precio_venta, stock_actual FROM productos WHERE id = ? AND activo = 1"
                );
                mysqli_stmt_bind_param($stmt, 'i', $pid);
                mysqli_stmt_execute($stmt);
                $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);

                if (!$prod) throw new Exception("Producto #$pid no encontrado.");
                if ($prod['stock_actual'] < $qty) {
                    throw new Exception("Stock insuficiente para \"{$prod['nombre']}\" (disponible: {$prod['stock_actual']}).");
                }

                $linea_sub = round($prod['precio_venta'] * $qty, 2);
                $subtotal += $linea_sub;
                $lineas[]  = [
                    'producto_id'     => $pid,
                    'cantidad'        => $qty,
                    'precio_unitario' => $prod['precio_venta'],
                    'subtotal'        => $linea_sub,
                ];
            }

            if (empty($lineas)) throw new Exception("No hay productos válidos en la venta.");

            $igv   = round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);

            // Insertar venta
            $stmt = mysqli_prepare($conn,
                "INSERT INTO ventas (usuario_id, cliente_id, subtotal, igv, total, estado, metodo_pago)
                 VALUES (?, ?, ?, ?, ?, 'completada', ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iiddds',
                $_SESSION['usuario_id'], $cliente_id,
                $subtotal, $igv, $total, $metodo_pago
            );
            mysqli_stmt_execute($stmt);
            $venta_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            // Insertar detalle y descontar stock
            foreach ($lineas as $l) {
                // Detalle
                $stmt = mysqli_prepare($conn,
                    "INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario, subtotal)
                     VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt, 'iiidd',
                    $venta_id, $l['producto_id'], $l['cantidad'],
                    $l['precio_unitario'], $l['subtotal']
                );
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                // Descontar stock (campo stock_actual según tu BD)
                $stmt = mysqli_prepare($conn,
                    "UPDATE productos SET stock_actual = stock_actual - ? WHERE id = ?"
                );
                mysqli_stmt_bind_param($stmt, 'ii', $l['cantidad'], $l['producto_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            // Generar comprobante
            $num_comp = strtoupper(substr($tipo_comp, 0, 1)) . '-' . str_pad($venta_id, 6, '0', STR_PAD_LEFT);
            $stmt = mysqli_prepare($conn,
                "INSERT INTO comprobantes (venta_id, tipo, numero) VALUES (?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iss', $venta_id, $tipo_comp, $num_comp);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            mysqli_commit($conn);

            // Redirigir al detalle / ticket
            header("Location: /stockflow/modules/ventas/detalle.php?id=$venta_id&nuevo=1");
            exit();

        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// ── Datos para el formulario ─────────────────────────────────
$clientes  = mysqli_query($conn, "SELECT id, nombre, telefono FROM clientes ORDER BY nombre");
$productos_db = mysqli_query($conn,
    "SELECT p.id, p.nombre, p.codigo, p.precio_venta, p.stock_actual, c.nombre AS categoria
     FROM productos p
     JOIN categorias c ON c.id = p.categoria_id
     WHERE p.activo = 1 AND p.stock_actual > 0
     ORDER BY p.nombre"
);

// Convertir productos a JSON para el JS
$prods_json = [];
while ($pr = mysqli_fetch_assoc($productos_db)) {
    $prods_json[$pr['id']] = $pr;
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <h1 class="page-title">Nueva Venta</h1>
    <a href="/stockflow/modules/ventas/index.php" class="btn btn-ghost">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert--danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="" id="formVenta">
<div class="venta-grid">

    <!-- ── Columna izquierda: productos ── -->
    <section class="card">
        <h2 class="card-title" style="margin-bottom:1rem">Productos</h2>

        <!-- Buscador de productos -->
        <div class="field">
            <label>Buscar y agregar producto</label>
            <select id="selectProducto" class="sf-select">
                <option value="">— Selecciona un producto —</option>
                <?php foreach ($prods_json as $p): ?>
                <option value="<?= $p['id'] ?>"
                        data-precio="<?= $p['precio_venta'] ?>"
                        data-stock="<?= $p['stock_actual'] ?>"
                        data-nombre="<?= htmlspecialchars($p['nombre']) ?>"
                        data-codigo="<?= htmlspecialchars($p['codigo'] ?? '') ?>">
                    [<?= htmlspecialchars($p['codigo'] ?? 'S/C') ?>]
                    <?= htmlspecialchars($p['nombre']) ?>
                    — S/ <?= number_format($p['precio_venta'], 2) ?>
                    (stock: <?= $p['stock_actual'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <button type="button" onclick="agregarProducto()" class="btn btn-secondary" style="margin-top:.5rem">
                + Agregar
            </button>
        </div>

        <!-- Tabla de líneas -->
        <div class="table-wrap">
            <table class="sf-table" id="tablaLineas">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio unit.</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="tbodyLineas">
                    <tr id="rowVacio">
                        <td colspan="5" style="text-align:center;color:var(--muted);padding:1.5rem">
                            Sin productos aún.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totales -->
        <div class="totales-box">
            <div class="totales-row">
                <span>Subtotal</span>
                <span id="lblSubtotal">S/ 0.00</span>
            </div>
            <div class="totales-row">
                <span>IGV (18%)</span>
                <span id="lblIgv">S/ 0.00</span>
            </div>
            <div class="totales-row totales-row--total">
                <span>TOTAL</span>
                <span id="lblTotal">S/ 0.00</span>
            </div>
        </div>
    </section>

    <!-- ── Columna derecha: datos de la venta ── -->
    <aside class="card" style="align-self:start">
        <h2 class="card-title" style="margin-bottom:1rem">Datos de la venta</h2>

        <div class="field">
            <label for="cliente_id">Cliente</label>
            <select name="cliente_id" id="cliente_id" class="sf-select" required>
                <option value="">— Selecciona —</option>
                <?php
                mysqli_data_seek($clientes, 0);
                while ($cl = mysqli_fetch_assoc($clientes)):
                ?>
                <option value="<?= $cl['id'] ?>">
                    <?= htmlspecialchars($cl['nombre']) ?>
                    <?= $cl['telefono'] ? "({$cl['telefono']})" : '' ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="field">
            <label for="metodo_pago">Método de pago</label>
            <select name="metodo_pago" id="metodo_pago" class="sf-select">
                <option value="efectivo">Efectivo</option>
                <option value="tarjeta">Tarjeta</option>
                <option value="transferencia">Transferencia</option>
                <option value="yape">Yape / Plin</option>
            </select>
        </div>

        <div class="field">
            <label for="tipo_comprobante">Comprobante</label>
            <select name="tipo_comprobante" id="tipo_comprobante" class="sf-select">
                <option value="ticket">Ticket</option>
                <option value="boleta">Boleta</option>
                <option value="factura">Factura</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="text-align: center;width:100%;margin-top:.5rem;"
                id="btnRegistrar" disabled>
            Registrar venta
        </button>
    </aside>
</div>
</form>

<style>
.venta-grid { display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start; }
.totales-box { border-top: 1px solid var(--border); margin-top: 1rem; padding-top: 1rem; }
.totales-row { display: flex; justify-content: space-between; padding: .3rem 0; color: var(--muted); font-size: .9rem; }
.totales-row--total { font-size: 1.15rem; font-weight: 600; color: var(--text); padding-top: .5rem; }
@media (max-width: 768px) { .venta-grid { grid-template-columns: 1fr; } }
</style>

<script>
// Catálogo de productos disponible en el front
const catalogo = <?= json_encode($prods_json, JSON_UNESCAPED_UNICODE) ?>;
// lineas[id] = { id, nombre, precio, cantidad, stock }
const lineas = {};

function fmt(n) { return 'S/ ' + parseFloat(n).toFixed(2); }

function recalcular() {
    let sub = 0;
    Object.values(lineas).forEach(l => { sub += l.precio * l.cantidad; });
    const igv   = sub * 0.18;
    const total = sub + igv;
    document.getElementById('lblSubtotal').textContent = fmt(sub);
    document.getElementById('lblIgv').textContent      = fmt(igv);
    document.getElementById('lblTotal').textContent    = fmt(total);
    document.getElementById('btnRegistrar').disabled   = Object.keys(lineas).length === 0;
}

function renderTabla() {
    const tbody = document.getElementById('tbodyLineas');
    tbody.innerHTML = '';
    const keys = Object.keys(lineas);

    if (keys.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:var(--muted);padding:1.5rem">Sin productos aún.</td></tr>';
        return;
    }

    keys.forEach(id => {
        const l = lineas[id];
        const sub = (l.precio * l.cantidad).toFixed(2);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                ${l.nombre}
                <input type="hidden" name="productos[${id}][id]"       value="${id}">
                <input type="hidden" name="productos[${id}][cantidad]"  id="hid_${id}" value="${l.cantidad}">
            </td>
            <td>${fmt(l.precio)}</td>
            <td>
                <input type="number" min="1" max="${l.stock}" value="${l.cantidad}"
                       style="width:70px" class="sf-input"
                       onchange="cambiarCantidad(${id}, this.value)">
            </td>
            <td>${fmt(sub)}</td>
            <td><button type="button" class="btn btn-xs btn-danger"
                        onclick="quitarLinea(${id})">✕</button></td>
        `;
        tbody.appendChild(tr);
    });
}

function agregarProducto() {
    const sel = document.getElementById('selectProducto');
    const id  = parseInt(sel.value);
    if (!id) return;
    const p = catalogo[id];

    if (lineas[id]) {
        // Ya está: sumar 1 si hay stock
        if (lineas[id].cantidad < lineas[id].stock) {
            lineas[id].cantidad++;
        } else {
            alert('No hay más stock disponible para este producto.');
        }
    } else {
        lineas[id] = { id, nombre: p.nombre, precio: parseFloat(p.precio_venta), cantidad: 1, stock: parseInt(p.stock_actual) };
    }

    renderTabla();
    recalcular();
    sel.value = '';
}

function cambiarCantidad(id, val) {
    const qty = Math.max(1, Math.min(parseInt(val) || 1, lineas[id].stock));
    lineas[id].cantidad = qty;
    document.getElementById('hid_' + id).value = qty;
    renderTabla();
    recalcular();
}

function quitarLinea(id) {
    delete lineas[id];
    renderTabla();
    recalcular();
}
</script>

<?php require_once '../../includes/footer.php'; ?>
