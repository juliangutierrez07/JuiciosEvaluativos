<?php
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrfValido($_POST['csrf_token'] ?? null)) {
    cerrarSesion();
    flash('success', 'Sesión cerrada correctamente.');
}

redirect(base('login.php'));
