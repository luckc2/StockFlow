<?php
// ============================================================
//  StockFlow — Editar producto
//  Archivo: modules/inventario/editar.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
if (!tieneRol(['admin','almacen'])) { header("Location: index.php"); exit(); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

$producto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM productos WHERE id=$id AND activo=1"));
if (!$producto) { header("Location: index.php"); exit(); }

$categorias  = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre");
$proveedores = mysqli_query($conn, "SELECT * FROM proveedores ORDER BY nombre");
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre        = trim($_POST['nombre']);
    $codigo        = trim($_POST['codigo']);
    $categoria_id  = (int)$_POST['categoria_id'];
    $proveedor_id  = (int)$_POST['proveedor_id'];
    $precio_compra = (float)str_replace(',','.',$_POST['precio_compra']);
    $precio_venta  = (float)str_replace(',','.',$_POST['precio_venta']);
    $stock_actual  = (int)$_POST['stock_actual'];
    $stock_minimo  = (int)$_POST['stock_minimo'];

    if (!$nombre || !$categoria_id || !$proveedor_id || $precio_venta <= 0) {
        $error = "Completa todos los campos obligatorios.";
    } else {
        // Verificar código duplicado excluyendo este producto
        if ($codigo) {
            $dup = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM productos WHERE codigo='" . mysqli_real_escape_string($conn,$codigo) . "' AND id != $id"
            ));
            if ($dup) $error = "Ya existe otro producto con ese código.";
        }
        if (!$error) {
            $stmt = mysqli_prepare($conn, "
                UPDATE productos SET
                    categoria_id=?, proveedor_id=?, nombre=?, codigo=?,
                    precio_compra=?, precio_venta=?, stock_actual=?, stock_minimo=?
                WHERE id=?
            ");
            mysqli_stmt_bind_param($stmt, 'iissddiii',
                $categoria_id, $proveedor_id, $nombre, $codigo,
                $precio_compra, $precio_venta, $stock_actual, $stock_minimo, $id
            );
            if (mysqli_stmt_execute($stmt)) {
                $producto = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM productos WHERE id=$id"));
                $success  = "Producto actualizado correctamente.";
            } else { $error = "Error al actualizar el producto."; }
        }
    }
}

// Historial de ventas de este producto
$historial = mysqli_query($conn, "
    SELECT v.id, v.fecha, dv.cantidad, dv.precio_unitario, dv.subtotal, c.nombre AS cliente
    FROM detalle_venta dv
    JOIN ventas   v ON dv.venta_id   = v.id
    JOIN clientes c ON v.cliente_id  = c.id
    WHERE dv.producto_id = $id
    ORDER BY v.fecha DESC
    LIMIT 8
");

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Editar producto</h1>
        <p class="page-subtitle"><?= htmlspecialchars($producto['nombre']) ?></p>
    </div>
    <a href="index.php" class="btn btn--ghost">← Volver</a>
</div>

<?php if ($success): ?><div class="alert alert--success"><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert--danger"><?= $error ?></div><?php endif; ?>

<div class="crear-grid">

    <!-- ── Formulario ── -->
    <div>
        <form method="POST" class="form">

            <div class="card" style="margin-bottom:24px">
                <div class="card__header"><h2 class="card__title">Información del producto</h2></div>
                <div class="card__body">

                    <div class="form__group">
                        <label class="form__label">Nombre *</label>
                        <input type="text" name="nombre" class="form__input" required
                               value="<?= htmlspecialchars($producto['nombre']) ?>">
                    </div>

                    <div class="form__row">
                        <div class="form__group">
                            <label class="form__label">Código / SKU</label>
                            <input type="text" name="codigo" class="form__input"
                                   value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>">
                        </div>
                        <div class="form__group">
                            <label class="form__label">Categoría *</label>
                            <select name="categoria_id" class="form__input" required>
                                <?php while ($c = mysqli_fetch_assoc($categorias)): ?>
                                <option value="<?= $c['id'] ?>" <?= $producto['categoria_id']==$c['id'] ? 'selected':'' ?>>
                                    <?= htmlspecialchars($c['nombre']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form__group">
                        <label class="form__label">Proveedor *</label>
                        <select name="proveedor_id" class="form__input" required>
                            <?php while ($p = mysqli_fetch_assoc($proveedores)): ?>
                            <option value="<?= $p['id'] ?>" <?= $producto['proveedor_id']==$p['id'] ? 'selected':'' ?>>
                                <?= htmlspecialchars($p['nombre']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                </div>
            </div>

            <div class="card">
                <div class="card__header"><h2 class="card__title">Precios y stock</h2></div>
                <div class="card__body">

                    <div class="form__row">
                        <div class="form__group">
                            <label class="form__label">Precio de compra (S/)</label>
                            <input type="number" name="precio_compra" class="form__input"
                                   step="0.01" min="0" id="precio_compra"
                                   value="<?= $producto['precio_compra'] ?>">
                        </div>
                        <div class="form__group">
                            <label class="form__label">Precio de venta (S/) *</label>
                            <input type="number" name="precio_venta" class="form__input"
                                   step="0.01" min="0.01" required id="precio_venta"
                                   value="<?= $producto['precio_venta'] ?>">
                        </div>
                    </div>

                    <div class="margen-preview" id="margen-preview">
                        <span class="margen-preview__label">Margen actual:</span>
                        <span class="margen-preview__valor" id="margen-valor"></span>
                    </div>

                    <div class="form__row" style="margin-top:16px">
                        <div class="form__group">
                            <label class="form__label">Stock actual</label>
                            <input type="number" name="stock_actual" class="form__input"
                                   min="0" value="<?= $producto['stock_actual'] ?>">
                            <?php if ($producto['stock_actual'] <= $producto['stock_minimo']): ?>
                                <span class="form__hint" style="color:var(--danger,#B91C1C)">
                                    ⚠️ Por debajo del mínimo (<?= $producto['stock_minimo'] ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="form__group">
                            <label class="form__label">Stock mínimo</label>
                            <input type="number" name="stock_minimo" class="form__input"
                                   min="0" value="<?= $producto['stock_minimo'] ?>">
                        </div>
                    </div>

                    <div class="form__actions" style="margin-top:24px">
                        <button type="submit" class="btn btn--primary">Guardar cambios</button>
                        <a href="index.php" class="btn btn--ghost">Cancelar</a>
                    </div>

                </div>
            </div>

        </form>
    </div>

    <!-- ── Historial de ventas del producto ── -->
    <div class="card" style="align-self:start">
        <div class="card__header">
            <h2 class="card__title">Últimas ventas</h2>
            <span class="badge badge--gray">del producto</span>
        </div>
        <div class="card__body p-0">
            <?php if (mysqli_num_rows($historial) === 0): ?>
                <p class="text-muted" style="padding:20px; font-size:.9rem">
                    Este producto aún no ha sido vendido.
                </p>
            <?php else: ?>
            <table class="table">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Cant.</th><th>Total</th></tr>
                </thead>
                <tbody>
                <?php while ($h = mysqli_fetch_assoc($historial)): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($h['fecha'])) ?></td>
                        <td><?= htmlspecialchars($h['cliente']) ?></td>
                        <td><?= $h['cantidad'] ?></td>
                        <td>S/ <?= number_format($h['subtotal'],2) ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
const pCompra = document.getElementById('precio_compra');
const pVenta  = document.getElementById('precio_venta');
const preview = document.getElementById('margen-preview');
const valor   = document.getElementById('margen-valor');
function calcMargen() {
    const c = parseFloat(pCompra.value) || 0;
    const v = parseFloat(pVenta.value)  || 0;
    if (v > 0) {
        const g   = v - c;
        const pct = c > 0 ? ((g/c)*100).toFixed(1) + '%' : '—';
        valor.textContent = `S/ ${g.toFixed(2)} (${pct})`;
        valor.style.color = g >= 0 ? 'var(--green,#0F6E56)' : 'var(--danger,#B91C1C)';
    }
}
calcMargen();
pCompra.addEventListener('input', calcMargen);
pVenta.addEventListener('input',  calcMargen);
</script>

<style>
.crear-grid { display:grid; grid-template-columns:1fr 380px; gap:24px; align-items:start; }
.form__row  { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.margen-preview { display:flex; align-items:center; gap:10px; background:var(--surface-2,#F5F6FA); border-radius:8px; padding:10px 14px; font-size:.9rem; margin-top:8px; }
.margen-preview__label { color:var(--text-muted,#6B7280); }
.margen-preview__valor { font-weight:700; }
@media(max-width:900px){ .crear-grid { grid-template-columns:1fr; } .form__row { grid-template-columns:1fr; } }
</style>

<?php require_once '../../includes/footer.php'; ?>
