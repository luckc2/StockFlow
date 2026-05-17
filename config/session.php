<?php
session_start();

// Función que verifica si el usuario está logueado
// Si no lo está, lo manda al login
function verificarSesion(){
    if(!isset($_SESSION['usuario_id'])){
        header("Location:  /stockflow/modules/auth/login.php");
        exit();
    }
}

// Función que verifica si el usuario tiene un rol específico
// Uso: verificarRol('admin') — si no es admin lo manda al dashboard
function verificarRol($rol){
    if($_SESSION['usuario_rol'] !== $rol){
        header("Location: /stockflow/dashboard.php");
        exit();
    }
}

// Función para saber si el usuario tiene alguno de varios roles
// Uso: tieneRol(['admin', 'vendedor'])
function tieneRol($roles){
    return in_array($_SESSION['usuario_rol'], $roles);
}
?>