<?php
require 'includes/base.php';
if (isset($_SESSION['usuario'])) {
    ir('panel.php');
}
$titulo = 'Ingresar';
include 'includes/cabecera.php';
?>
<h1>Ingresar al sistema</h1>
<form method="post" action="validar_login.php">
    <?php csrf(); ?>
    <label>Usuario <input type="text" name="usuario" maxlength="50"></label>
    <label>Contraseña <input type="password" name="contrasena" maxlength="100"></label>
    <button class="boton">Ingresar</button>
</form>
<?php include 'includes/pie.php'; ?>
