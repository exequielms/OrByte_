<?php
// cerrar sesión: solo por POST (el botón está en perfil.php)
require 'includes/base.php';
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('panel.php');
}
session_destroy();
ir('login.php');
