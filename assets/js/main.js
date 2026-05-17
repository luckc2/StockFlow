

// 1. ALERTAS QUE SE CIERRAN SOLAS

// Las alertas de éxito (.alert--success) desaparecen a los 4s
// Las de error se quedan hasta que el usuario las cierre

document.querySelectorAll('.alert--success').forEach(function(el) {
    // Esperar 4 segundos, luego animar la salida
    setTimeout(function() {
        el.style.transition = 'opacity .5s, margin .5s, padding .5s';
        el.style.opacity    = '0';
        el.style.margin     = '0';
        el.style.padding    = '0';
        // Cuando termina la animación, quitar del DOM
        setTimeout(function() { el.remove(); }, 500);
    }, 4000);
});


// 2. CONFIRMACIÓN ANTES DE ELIMINAR

// Cualquier enlace o botón con data-confirm="mensaje"
// pedirá confirmación antes de continuar.
// Uso en HTML: <a href="eliminar.php?id=5" data-confirm="¿Eliminar este producto?">

document.querySelectorAll('[data-confirm]').forEach(function(el) {
    el.addEventListener('click', function(e) {
        var msg = el.getAttribute('data-confirm') || '¿Estás seguro?';
        if (!confirm(msg)) {
            e.preventDefault(); // cancela el click si el usuario dice No
        }
    });
});


// 3. MARCAR LINK ACTIVO EN EL NAVBAR

// header.php ya hace esto con PHP (strpos en REQUEST_URI),
// pero este JS lo refuerza por si alguna ruta no coincide exactamente.

(function() {
    var ruta = window.location.pathname; // ej: /stockflow/modules/inventario/index.php
    document.querySelectorAll('.topnav__link').forEach(function(a) {
        // Si la URL del link está contenida en la ruta actual → activo
        if (ruta.indexOf(a.getAttribute('href')) !== -1) {
            a.classList.add('active');
        }
    });
})();



// 4. ALERTA DE STOCK BAJO → agregar clase al body

// Si existe la barra de alerta de stock, el body necesita
// la clase has-stock-alert para que el contenido baje un poco más
// (ver style.css: body.has-stock-alert .sf-main)

if (document.querySelector('.alert-stock')) {
    document.body.classList.add('has-stock-alert');
}



// 5. INPUTS NUMÉRICOS — no permitir negativos

// Cualquier <input type="number" min="0"> no dejará escribir
// un número negativo aunque el usuario lo intente.

document.querySelectorAll('input[type="number"]').forEach(function(inp) {
    inp.addEventListener('input', function() {
        var min = parseFloat(inp.getAttribute('min') ?? 0);
        if (parseFloat(inp.value) < min) inp.value = min;
    });
});



// 6. FUNCIÓN GLOBAL: mostrar mensaje flash temporal

// Se puede llamar desde cualquier página:
// SF.flash('Producto guardado', 'success')
// SF.flash('Error al guardar', 'danger')

window.SF = {
    flash: function(mensaje, tipo) {
        tipo = tipo || 'success';
        var div = document.createElement('div');
        div.className = 'alert alert--' + tipo;
        div.style.cssText = 'position:fixed;top:70px;right:1.5rem;z-index:999;min-width:260px;max-width:380px;box-shadow:0 8px 24px rgba(0,0,0,.4)';
        div.textContent = mensaje;
        document.body.appendChild(div);
        setTimeout(function() {
            div.style.transition = 'opacity .4s';
            div.style.opacity = '0';
            setTimeout(function() { div.remove(); }, 400);
        }, 3500);
    }
};
