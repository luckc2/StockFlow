<?php
// includes/footer.php — StockFlow
// Se incluye AL FINAL de cada página, cierra el <main> que abrió header.php
?>
</main><!-- cierra .sf-main que abrió header.php -->

<footer class="sf-footer">
    <span>StockFlow &copy; <?= date('Y') ?></span>
    <span>Sesión: <strong><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></strong>
          &mdash; Rol: <strong><?= htmlspecialchars($_SESSION['usuario_rol']) ?></strong>
    </span>
</footer>

<script src="/stockflow/assets/js/main.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
