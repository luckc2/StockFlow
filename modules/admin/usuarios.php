<?php
// ============================================================
//  StockFlow — Gestión de usuarios
//  Archivo: modules/admin/usuarios.php
//  Solo accesible por admin
// ============================================================
require_once '../../config/database.php';
require_once '../../config/session.php';
verificarSesion();
verificarRol('admin');

$error   = '';
$success = '';

// ── Crear nuevo usuario ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $rol      = $_POST['rol'];

    if ($nombre && $email && $password && $rol) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn,
            "INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $nombre, $email, $hash, $rol);
        if (mysqli_stmt_execute($stmt)) {
            $success = "Usuario <strong>$nombre</strong> creado correctamente.";
        } else {
            $error = "El email ya existe o hubo un error al guardar.";
        }
    } else {
        $error = "Completa todos los campos obligatorios.";
    }
}

// ── Obtener lista de usuarios ────────────────────────────────
$usuarios = mysqli_query($conn, "SELECT * FROM usuarios ORDER BY rol, nombre");

require_once '../../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de usuarios</h1>
        <p class="page-subtitle">Administra quién tiene acceso al sistema</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert--success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert--danger"><?= $error ?></div>
<?php endif; ?>

<div class="admin-grid">

    <!-- ── Lista de usuarios ── -->
    <div class="card">
        <div class="card__header">
            <h2 class="card__title">Usuarios del sistema</h2>
            <span class="badge badge--gray"><?= mysqli_num_rows($usuarios) ?> usuarios</span>
        </div>
        <div class="card__body p-0">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registrado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($u = mysqli_fetch_assoc($usuarios)): ?>
                    <tr class="<?= !$u['activo'] ? 'row--inactive' : '' ?>">
                        <td class="text-muted"><?= $u['id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                            <?php if ($u['id'] === $_SESSION['usuario_id']): ?>
                                <span class="badge badge--blue" style="font-size:.65rem">Tú</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge badge--<?= $u['rol'] ?>">
                                <?= strtoupper($u['rol']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['activo']): ?>
                                <span class="badge badge--success">Activo</span>
                            <?php else: ?>
                                <span class="badge badge--danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?= date('d/m/Y', strtotime($u['created_at'])) ?>
                        </td>
                        <td>
                            <?php if ($u['id'] !== $_SESSION['usuario_id']): ?>
                                <a href="toggle_usuario.php?id=<?= $u['id'] ?>"
                                   class="btn btn--sm <?= $u['activo'] ? 'btn--danger-outline' : 'btn--success-outline' ?>"
                                   data-confirm="<?= $u['activo'] ? '¿Desactivar este usuario?' : '¿Activar este usuario?' ?>">
                                    <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.8rem">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── Crear nuevo usuario ── -->
    <div class="card mt-4">
        <div class="card__header">
            <h2 class="card__title">Nuevo usuario</h2>
        </div>
        <div class="card__body">
            <form method="POST" class="form">

                <div class="form__group">
                    <label class="form__label">Nombre completo *</label>
                    <input type="text" name="nombre" class="form__input"
                           placeholder="Ej: María Torres" required
                           value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
                </div>

                <div class="form__group">
                    <label class="form__label">Email *</label>
                    <input type="email" name="email" class="form__input"
                           placeholder="correo@empresa.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form__group">
                    <label class="form__label">Contraseña *</label>
                    <input type="password" name="password" class="form__input"
                           placeholder="Mínimo 6 caracteres" required minlength="6">
                </div>

                <div class="form__group">
                    <label class="form__label">Rol *</label>
                    <select name="rol" class="form__input" required>
                        <option value="">Seleccionar rol...</option>
                        <option value="vendedor"  <?= (($_POST['rol'] ?? '') === 'vendedor')  ? 'selected' : '' ?>>Vendedor</option>
                        <option value="almacen"   <?= (($_POST['rol'] ?? '') === 'almacen')   ? 'selected' : '' ?>>Almacén</option>
                        <option value="admin"     <?= (($_POST['rol'] ?? '') === 'admin')     ? 'selected' : '' ?>>Administrador</option>
                    </select>
                    <span class="form__hint">
                        <strong>Vendedor:</strong> ventas y clientes.
                        <strong>Almacén:</strong> inventario.
                        <strong>Admin:</strong> acceso total.
                    </span>
                </div>

                <div class="form__actions">
                    <button type="submit" class="btn btn--primary">Crear usuario</button>
                </div>

            </form>
        </div>
    </div>

</div>

<?php require_once '../../includes/footer.php'; ?>
