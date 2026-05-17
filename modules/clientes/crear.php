<?php
// ============================================================
//  StockFlow — Crear cliente
//  Archivo: modules/clientes/crear.php
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre']);
    $telefono  = trim($_POST['telefono']);
    $email     = trim($_POST['email']);
    $direccion = trim($_POST['direccion']);

    if (!$nombre) {
        $error = "El nombre del cliente es obligatorio.";
    } else {
        // Verificar email duplicado si se ingresó
        if ($email) {
            $check = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT id FROM clientes WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'"
            ));
            if ($check) {
                $error = "Ya existe un cliente con ese email.";
            }
        }

        if (!$error) {
            $stmt = mysqli_prepare($conn,
                "INSERT INTO clientes (nombre, telefono, email, direccion) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $nombre, $telefono, $email, $direccion);
            if (mysqli_stmt_execute($stmt)) {
                $nuevo_id = mysqli_insert_id($conn);
                header("Location: index.php?msg=Cliente+creado+correctamente");
                exit();
            } else {
                $error = "Error al guardar el cliente.";
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Nuevo cliente</h1>
        <p class="page-subtitle">Completa los datos del cliente</p>
    </div>
    <a href="index.php" class="btn btn--ghost">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert--danger"><?= $error ?></div>
<?php endif; ?>

<div class="card" style="max-width: 680px">
    <div class="card__header">
        <h2 class="card__title">Datos del cliente</h2>
    </div>
    <div class="card__body">
        <form method="POST" class="form">

            <div class="form__group">
                <label class="form__label">Nombre completo *</label>
                <input type="text" name="nombre" class="form__input"
                       placeholder="Ej: Juan Pérez García" required
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
            </div>

            <div class="form__row">
                <div class="form__group">
                    <label class="form__label">Teléfono</label>
                    <input type="text" name="telefono" class="form__input"
                           placeholder="987 654 321"
                           value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                </div>
                <div class="form__group">
                    <label class="form__label">Email</label>
                    <input type="email" name="email" class="form__input"
                           placeholder="cliente@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form__group">
                <label class="form__label">Dirección</label>
                <input type="text" name="direccion" class="form__input"
                       placeholder="Av. Principal 123, Distrito, Lima"
                       value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
            </div>

            <div class="form__actions">
                <button type="submit" class="btn btn--primary">Guardar cliente</button>
                <a href="index.php" class="btn btn--ghost">Cancelar</a>
            </div>

        </form>
    </div>
</div>

<style>
.form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 600px) {
    .form__row { grid-template-columns: 1fr; }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
