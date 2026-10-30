<?php
// Página de inicio: manda al panel si hay sesión, o al login si no
require 'includes/base.php';
if (isset($_SESSION['usuario'])) {
    ir('panel.php');
}
ir('login.php');
