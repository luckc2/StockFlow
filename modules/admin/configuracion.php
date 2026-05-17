<?php
// ============================================================
//  StockFlow — Configuración del sistema
//  Archivo: modules/admin/configuracion.php
//  Solo accesible por admin
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

$success = '';
$error   = '';

// ── Guardar configuración ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'empresa_nombre', 'empresa_ruc', 'empresa_direccion',
        'empresa_telefono', 'empresa_email',
        'igv_porcentaje', 'moneda_simbolo', 'comprobante_defecto'
    ];

    $ok = true;
    foreach ($campos as $clave) {
        $valor = trim($_POST[$clave] ?? '');
        $stmt  = mysqli_prepare($conn,
            "INSERT INTO configuracion (clave, valor)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        mysqli_stmt_bind_param($stmt, 'ss', $clave, $valor);
        if (!mysqli_stmt_execute($stmt)) { $ok = false; }
    }

    $success = $ok
        ? "Configuración guardada correctamente."
        : "Hubo un error al guardar algunos valores.";
}

// ── Leer configuración actual ────────────────────────────────
// Convertimos a array clave => valor para usarlo fácil en el form
$config = [];
$result = mysqli_query($conn, "SELECT clave, valor FROM configuracion");
while ($row = mysqli_fetch_assoc($result)) {
    $config[$row['clave']] = $row['valor'];
}

// Helper: valor actual o vacío
function cfg($config, $clave, $default = '') {
    return htmlspecialchars($config[$clave] ?? $default);
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Configuración del sistema</h1>
        <p class="page-subtitle">Datos de la empresa y preferencias del sistema</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert--success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert--danger"><?= $error ?></div>
<?php endif; ?>

<form method="POST" class="form">
<div class="config-grid">

    <!-- ── Datos de la empresa ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Datos de la empresa</h2>
            <p class="card__subtitle">Aparecen en tickets y reportes</p>
        </div>
        <div class="card__body">

            <div class="form__group">
                <label class="form__label">Nombre del negocio *</label>
                <input type="text" name="empresa_nombre" class="form__input"
                       placeholder="Ej: Ferretería El Maestro SAC"
                       value="<?= cfg($config, 'empresa_nombre') ?>" required>
            </div>

            <div class="form__group">
                <label class="form__label">RUC</label>
                <input type="text" name="empresa_ruc" class="form__input"
                       placeholder="20000000000" maxlength="11"
                       value="<?= cfg($config, 'empresa_ruc') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Dirección</label>
                <input type="text" name="empresa_direccion" class="form__input"
                       placeholder="Av. Principal 123, Lima"
                       value="<?= cfg($config, 'empresa_direccion') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Teléfono</label>
                <input type="text" name="empresa_telefono" class="form__input"
                       placeholder="01-234-5678 / 987 654 321"
                       value="<?= cfg($config, 'empresa_telefono') ?>">
            </div>

            <div class="form__group">
                <label class="form__label">Email de contacto</label>
                <input type="email" name="empresa_email" class="form__input"
                       placeholder="contacto@empresa.com"
                       value="<?= cfg($config, 'empresa_email') ?>">
            </div>

        </div>
    </div>

    <!-- ── Configuración de ventas ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Configuración de ventas</h2>
            <p class="card__subtitle">Afectan los cálculos y los comprobantes</p>
        </div>
        <div class="card__body">

            <div class="form__group">
                <label class="form__label">Porcentaje de IGV (%)</label>
                <input type="number" name="igv_porcentaje" class="form__input"
                       min="0" max="100" step="0.01"
                       placeholder="18"
                       value="<?= cfg($config, 'igv_porcentaje', '18') ?>">
                <span class="form__hint">En Perú el IGV estándar es 18%.</span>
            </div>

            <div class="form__group">
                <label class="form__label">Símbolo de moneda</label>
                <input type="text" name="moneda_simbolo" class="form__input"
                       placeholder="S/" maxlength="5"
                       value="<?= cfg($config, 'moneda_simbolo', 'S/') ?>">
                <span class="form__hint">Ej: S/ para soles, $ para dólares.</span>
            </div>

            <div class="form__group">
                <label class="form__label">Comprobante por defecto</label>
                <select name="comprobante_defecto" class="form__input">
                    <?php
                    $actual = $config['comprobante_defecto'] ?? 'ticket';
                    $tipos  = ['ticket' => 'Ticket', 'boleta' => 'Boleta', 'factura' => 'Factura'];
                    foreach ($tipos as $val => $label):
                    ?>
                    <option value="<?= $val ?>" <?= $actual === $val ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <span class="form__hint">Se usará al generar comprobantes en ventas.</span>
            </div>

            <!-- Vista previa del ticket con los datos actuales -->
            <div class="ticket-preview">
                <p class="ticket-preview__label">Vista previa del encabezado del ticket</p>
                <div class="ticket-preview__body" id="preview-ticket">
                    <strong id="prev-nombre"><?= cfg($config, 'empresa_nombre', 'Nombre del negocio') ?></strong><br>
                    <span id="prev-ruc">RUC: <?= cfg($config, 'empresa_ruc', '—') ?></span><br>
                    <span id="prev-dir"><?= cfg($config, 'empresa_direccion', '—') ?></span><br>
                    <span id="prev-tel"><?= cfg($config, 'empresa_telefono', '—') ?></span>
                </div>
            </div>

        </div>
    </div>

</div><!-- .config-grid -->

<!-- Botón guardar fuera del grid pero dentro del form -->
<div class="form__actions" style="margin-top: 24px;">
    <button type="submit" class="btn btn--primary">Guardar configuración</button>
    <a href="/stockflow/dashboard.php" class="btn btn--ghost">Cancelar</a>
</div>

</form>

<!-- Preview en tiempo real con JS -->
<script>
const campos = {
    empresa_nombre:    'prev-nombre',
    empresa_ruc:       'prev-ruc',
    empresa_direccion: 'prev-dir',
    empresa_telefono:  'prev-tel',
};

Object.entries(campos).forEach(([name, id]) => {
    const input = document.querySelector(`[name="${name}"]`);
    const span  = document.getElementById(id);
    if (!input || !span) return;

    input.addEventListener('input', () => {
        if (name === 'empresa_ruc') {
            span.textContent = 'RUC: ' + (input.value || '—');
        } else {
            span.textContent = input.value || '—';
        }
    });
});
</script>

<!-- Estilos específicos de esta página -->
<style>
.config-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.card__subtitle {
    font-size: .8rem;
    color: var(--text-muted, #6B7280);
    margin-top: 2px;
}

.ticket-preview {
    margin-top: 24px;
    border: 1px dashed var(--border, #E0DED8);
    border-radius: 8px;
    overflow: hidden;
}

.ticket-preview__label {
    background: var(--surface-2, #F5F6FA);
    padding: 6px 12px;
    font-size: .75rem;
    color: var(--text-muted, #6B7280);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .5px;
    border-bottom: 1px dashed var(--border, #E0DED8);
}

.ticket-preview__body {
    padding: 14px 16px;
    font-family: monospace;
    font-size: .85rem;
    line-height: 1.7;
    color: var(--text, #1A1A1A);
}

@media (max-width: 768px) {
    .config-grid { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
