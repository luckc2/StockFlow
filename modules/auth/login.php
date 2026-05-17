<?php
// modules/auth/login.php — StockFlow
require_once '../../config/session.php';
require_once '../../config/database.php';

// Si ya está logueado, mandarlo al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: /stockflow/dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =      $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Por favor ingresa tu correo y contraseña.';
    } else {
        // Buscar usuario activo por email
        $stmt = mysqli_prepare($conn,
            "SELECT id, nombre, password_hash, rol FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $usuario = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);

            $_SESSION['usuario_id']     = $usuario['id'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_rol']    = $usuario['rol'];

            header("Location: /stockflow/dashboard.php");
            exit();
        } else {
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockFlow — Iniciar sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #0f1117;
            --surface:   #1a1d27;
            --border:    #2a2d3a;
            --accent:    #4f8ef7;
            --accent-h:  #6aa1ff;
            --danger:    #f75b5b;
            --text:      #e8eaf0;
            --muted:     #7a7f96;
            --radius:    12px;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background-image:
                radial-gradient(ellipse 60% 50% at 50% -10%, rgba(79,142,247,.18) 0%, transparent 70%);
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 2.5rem 2rem;
            box-shadow: 0 24px 64px rgba(0,0,0,.5);
            animation: fadeUp .4s ease both;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 38px; height: 38px;
            background: var(--accent);
            border-radius: 10px;
            display: grid; place-items: center;
            font-family: 'DM Mono', monospace;
            font-size: 1rem;
            color: #fff;
            font-weight: 500;
        }

        .logo-text {
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: -.5px;
        }

        h1 { font-size: 1.4rem; font-weight: 600; margin-bottom: .4rem; }
        .subtitle { color: var(--muted); font-size: .9rem; margin-bottom: 1.8rem; }

        .field { margin-bottom: 1.1rem; }
        label  { display: block; font-size: .83rem; color: var(--muted); margin-bottom: .45rem; font-weight: 500; }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: .75rem 1rem;
            color: var(--text);
            font-family: inherit;
            font-size: .95rem;
            outline: none;
            transition: border-color .2s;
        }

        input:focus { border-color: var(--accent); }

        .error-box {
            background: rgba(247,91,91,.1);
            border: 1px solid rgba(247,91,91,.35);
            color: var(--danger);
            border-radius: var(--radius);
            padding: .7rem 1rem;
            font-size: .88rem;
            margin-bottom: 1.2rem;
        }

        .btn-primary {
            width: 100%;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: var(--radius);
            padding: .85rem;
            font-family: inherit;
            font-size: .95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .2s, transform .1s;
            margin-top: .4rem;
        }

        .btn-primary:hover  { background: var(--accent-h); }
        .btn-primary:active { transform: scale(.98); }

        .footer-note {
            text-align: center;
            margin-top: 1.6rem;
            font-size: .8rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">SF</div>
        <span class="logo-text">StockFlow</span>
    </div>

    <h1>Bienvenido de nuevo</h1>
    <p class="subtitle">Ingresa tus credenciales para continuar.</p>

    <?php if ($error): ?>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="field">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" name="email"
                   placeholder="usuario@empresa.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
        </div>
        <div class="field">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" required>
        </div>
        <button type="submit" class="btn-primary">Iniciar sesión</button>
    </form>

    <p class="footer-note">StockFlow &copy; <?= date('Y') ?> — Sistema interno</p>
</div>
</body>
</html>
