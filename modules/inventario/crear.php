<?php
// ============================================================
//  StockFlow — Crear producto
//  Archivo: modules/inventario/crear.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
if (!tieneRol(['admin','almacen'])) { header("Location: index.php"); exit(); }

$categorias  = mysqli_query($conn, "SELECT * FROM categorias ORDER BY nombre");
$proveedores = mysqli_query($conn, "SELECT * FROM proveedores ORDER BY nombre");
$error = '';

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
        $error = "Completa todos los campos obligatorios y asegúrate que el precio de venta sea mayor a 0.";
    } else {
        if ($codigo) {
            $dup = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM productos WHERE codigo = '" . mysqli_real_escape_string($conn, $codigo) . "'"
            ));
            if ($dup) $error = "Ya existe un producto con ese código.";
        }
        if (!$error) {
            $stmt = mysqli_prepare($conn, "
                INSERT INTO productos (categoria_id, proveedor_id, nombre, codigo, precio_compra, precio_venta, stock_actual, stock_minimo)
                VALUES (?,?,?,?,?,?,?,?)
            ");
            mysqli_stmt_bind_param($stmt, 'iissddii',
                $categoria_id, $proveedor_id, $nombre, $codigo,
                $precio_compra, $precio_venta, $stock_actual, $stock_minimo
            );
            if (mysqli_stmt_execute($stmt)) {
                header("Location: index.php?msg=Producto+creado+correctamente");
                exit();
            } else { $error = "Error al guardar el producto."; }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nuevo producto</h1>
        <p class="page-subtitle">Agrega un producto al inventario</p>
    </div>
    <a href="index.php" class="btn btn--ghost">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert--danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" class="form">
<div class="crear-grid">

    <div class="card">
        <div class="card__header"><h2 class="card__title">Información del producto</h2></div>
        <div class="card__body">

            <div class="form__group">
                <label class="form__label">Nombre *</label>
                <input type="text" name="nombre" class="form__input" required
                       placeholder="Ej: Cable USB Tipo C 1m"
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
            </div>

            <div class="form__row">
                <div class="form__group">
                    <label class="form__label">Código / SKU</label>
                    <input type="text" name="codigo" class="form__input"
                           placeholder="Ej: ELEC-001"
                           value="<?= htmlspecialchars($_POST['codigo'] ?? '') ?>">
                    <span class="form__hint">Debe ser único. Déjalo vacío si no tienes.</span>
                </div>
                <div class="form__group">
                    <label class="form__label">Categoría *</label>
                    <select name="categoria_id" class="form__input" required>
                        <option value="">Seleccionar...</option>
                        <?php while ($c = mysqli_fetch_assoc($categorias)): ?>
                        <option value="<?= $c['id'] ?>" <?= (($_POST['categoria_id'] ?? '') == $c['id']) ? 'selected':'' ?>>
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form__group">
                <label class="form__label">Proveedor *</label>
                <select name="proveedor_id" class="form__input" required>
                    <option value="">Seleccionar...</option>
                    <?php while ($p = mysqli_fetch_assoc($proveedores)): ?>
                    <option value="<?= $p['id'] ?>" <?= (($_POST['proveedor_id'] ?? '') == $p['id']) ? 'selected':'' ?>>
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
                           step="0.01" min="0" placeholder="0.00" id="precio_compra"
                           value="<?= $_POST['precio_compra'] ?? '' ?>">
                    <span class="form__hint">Lo que pagas al proveedor.</span>
                </div>
                <div class="form__group">
                    <label class="form__label">Precio de venta (S/) *</label>
                    <input type="number" name="precio_venta" class="form__input"
                           step="0.01" min="0.01" placeholder="0.00" required id="precio_venta"
                           value="<?= $_POST['precio_venta'] ?? '' ?>">
                    <span class="form__hint">Lo que cobra al cliente.</span>
                </div>
            </div>

            <div class="margen-preview" id="margen-preview" style="display:none">
                <span class="margen-preview__label">Margen de ganancia:</span>
                <span class="margen-preview__valor" id="margen-valor"></span>
            </div>

            <div class="form__row" style="margin-top:16px">
                <div class="form__group">
                    <label class="form__label">Stock inicial</label>
                    <input type="number" name="stock_actual" class="form__input"
                           min="0" placeholder="0" value="<?= $_POST['stock_actual'] ?? '0' ?>">
                    <span class="form__hint">Unidades que tienes ahora.</span>
                </div>
                <div class="form__group">
                    <label class="form__label">Stock mínimo</label>
                    <input type="number" name="stock_minimo" class="form__input"
                           min="0" placeholder="5" value="<?= $_POST['stock_minimo'] ?? '5' ?>">
                    <span class="form__hint">Alerta cuando baje de este número.</span>
                </div>
            </div>

        </div>
    </div>

</div>

<div class="form__actions" style="margin-top:24px">
    <button type="submit" class="btn btn--primary">Guardar producto</button>
    <a href="index.php" class="btn btn--ghost">Cancelar</a>
</div>
</form>

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
        preview.style.display = 'flex';
        valor.textContent = `S/ ${g.toFixed(2)} (${pct})`;
        valor.style.color = g >= 0 ? 'var(--green,#0F6E56)' : 'var(--danger,#B91C1C)';
    } else { preview.style.display = 'none'; }
}
pCompra.addEventListener('input', calcMargen);
pVenta.addEventListener('input',  calcMargen);
</script>

<style>
.crear-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
.form__row  { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.margen-preview { display:flex; align-items:center; gap:10px; background:var(--surface-2,#F5F6FA); border-radius:8px; padding:10px 14px; font-size:.9rem; margin-top:8px; }
.margen-preview__label { color:var(--text-muted,#6B7280); }
.margen-preview__valor { font-weight:700; }
@media(max-width:900px){ .crear-grid,.form__row { grid-template-columns:1fr; } }
</style>

<?php require_once '../../includes/footer.php'; ?>
