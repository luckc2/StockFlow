<?php
// ============================================================
//  StockFlow — Editar cliente
//  Archivo: modules/clientes/editar.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: index.php"); exit(); }

$cliente = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM clientes WHERE id = $id"));
if (!$cliente) { header("Location: index.php"); exit(); }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $email     = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);

    if (!$nombre) {
        $error = "El nombre del cliente es obligatorio.";
    } else {
        // Verificar email duplicado (excluyendo el cliente actual)
        if ($email) {
            $check = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM clientes
                 WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'
                   AND id != $id"
            ));
            if ($check) {
                $error = "Ya existe otro cliente con ese email.";
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conn,
                "UPDATE clientes SET nombre=?, telefono=?, email=?, direccion=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $nombre, $telefono, $email, $direccion, $id);
            if (mysqli_stmt_execute($stmt)) {
                // Refrescar datos del cliente
                $cliente = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM clientes WHERE id=$id"));
                $success = "Cliente actualizado correctamente.";
            } else {
                $error = "Error al actualizar el cliente.";
            }
        }
    }
}

// Estadísticas del cliente
$stats = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*)             AS total_compras,
        COALESCE(SUM(total), 0) AS monto_total,
        MAX(fecha)           AS ultima_compra
    FROM ventas
    WHERE cliente_id = $id AND estado = 'completada'
"));

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Editar cliente</h1>
        <p class="page-subtitle"><?= htmlspecialchars($cliente['nombre']) ?></p>
    </div>
    <div style="display:flex; gap:8px">
        <a href="historial.php?id=<?= $id ?>" class="btn btn--ghost">Ver historial</a>
        <a href="index.php" class="btn btn--ghost">← Volver</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert--success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert--danger"><?= $error ?></div>
<?php endif; ?>

<div class="editar-grid">

    <!-- ── Formulario ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Datos del cliente</h2>
        </div>
        <div class="card__body">
            <form method="POST" class="form">

                <div class="form__group">
                    <label class="form__label">Nombre completo *</label>
                    <input type="text" name="nombre" class="form__input" required
                           value="<?= htmlspecialchars($cliente['nombre']) ?>">
                </div>

                <div class="form__row">
                    <div class="form__group">
                        <label class="form__label">Teléfono</label>
                        <input type="text" name="telefono" class="form__input"
                               placeholder="987 654 321"
                               value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                    </div>
                    <div class="form__group">
                        <label class="form__label">Email</label>
                        <input type="email" name="email" class="form__input"
                               placeholder="cliente@email.com"
                               value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                    </div>
                </div>

                <div class="form__group">
                    <label class="form__label">Dirección</label>
                    <input type="text" name="direccion" class="form__input"
                           placeholder="Av. Principal 123, Lima"
                           value="<?= htmlspecialchars($cliente['direccion'] ?? '') ?>">
                </div>

                <div class="form__group">
                    <label class="form__label">Registrado el</label>
                    <input type="text" class="form__input" disabled
                           value="<?= date('d/m/Y H:i', strtotime($cliente['created_at'])) ?>">
                </div>

                <div class="form__actions">
                    <button type="submit" class="btn btn--primary">Guardar cambios</button>
                    <a href="index.php" class="btn btn--ghost">Cancelar</a>
                </div>

            </form>
        </div>
    </div>

    <!-- ── Estadísticas del cliente ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Estadísticas</h2>
        </div>
        <div class="card__body">
            <div class="stat-list">
                <div class="stat-item">
                    <span class="stat-item__icon"></span>
                    <div class="stat-item__info">
                        <span class="stat-item__value"><?= $stats['total_compras'] ?></span>
                        <span class="stat-item__label">Compras realizadas</span>
                    </div>
                </div>
                <div class="stat-item">
                    <span class="stat-item__icon"></span>
                    <div class="stat-item__info">
                        <span class="stat-item__value">S/ <?= number_format($stats['monto_total'], 2) ?></span>
                        <span class="stat-item__label">Total gastado</span>
                    </div>
                </div>
                <div class="stat-item">
                    <span class="stat-item__icon"></span>
                    <div class="stat-item__info">
                        <span class="stat-item__value">
                            <?= $stats['ultima_compra']
                                ? date('d/m/Y', strtotime($stats['ultima_compra']))
                                : '—' ?>
                        </span>
                        <span class="stat-item__label">Última compra</span>
                    </div>
                </div>
            </div>

            <?php if ($stats['total_compras'] > 0): ?>
            <div style="margin-top:20px">
                <a href="historial.php?id=<?= $id ?>" class="btn btn--primary" style="width:100%; text-align:center">
                    Ver historial completo →
                </a>
            </div>
            <?php else: ?>
            <p class="text-muted" style="margin-top:20px; font-size:.9rem">
                Este cliente aún no tiene compras registradas.
            </p>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
.editar-grid {
    display: grid;
    grid-template-columns: 1fr 360px;
    gap: 24px;
    align-items: start;
}
.form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.stat-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.stat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 14px;
    background: var(--surface-2, #F5F6FA);
    border-radius: 8px;
}
.stat-item__icon  { font-size: 1.6rem; }
.stat-item__value { display: block; font-size: 1.2rem; font-weight: 700; color: black;}
.stat-item__label { display: block; font-size: .8rem; color: var(--text-muted, #6B7280); }

@media (max-width: 900px) {
    .editar-grid { grid-template-columns: 1fr; }
    .form__row   { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
