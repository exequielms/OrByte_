<?php
// Pantalla de ambulancias (tabla y formulario)
require 'includes/base.php';
entrar(TRANSP);

$lista = consulta("SELECT * FROM ambulancias ORDER BY patente");

// con ?id=... se edita esa ambulancia, si no viene el formulario queda vacío
$amb = buscar_uno("SELECT * FROM ambulancias WHERE id_ambulancia=?", "i", (int)($_GET['id'] ?? 0));
$editando = ($amb !== null);
if (!$editando) {
    $amb = ['id_ambulancia' => '', 'patente' => '', 'modelo' => '', 'anio' => '', 'estado' => ''];
}

$titulo = 'Ambulancias';
include 'includes/cabecera.php';
?>
<h1>Gestión de ambulancias</h1>
<table class="tabla">
    <tr><th>Patente</th><th>Modelo</th><th>Año</th><th>Estado</th><th>Acciones</th></tr>
    <?php foreach ($lista as $a) { ?>
    <tr>
        <td><?= e($a['patente']) ?></td><td><?= e($a['modelo']) ?></td><td><?= e($a['anio']) ?></td><td><?= e($a['estado']) ?></td>
        <td>
            <a href="?id=<?= e($a['id_ambulancia']) ?>">Editar</a> |
            <form method="post" action="guardar_ambulancia.php" class="inline" data-confirmar="¿Seguro que quiere eliminar?">
                <?php csrf(); ?><input type="hidden" name="borrar" value="<?= e($a['id_ambulancia']) ?>"><button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="5">No hay ambulancias cargadas.</td></tr><?php } ?>
</table>

<h2><?= $editando ? 'Editar' : 'Nueva' ?> ambulancia</h2>
<form method="post" action="guardar_ambulancia.php">
    <?php csrf(); ?>
    <input type="hidden" name="id" value="<?= e($amb['id_ambulancia']) ?>">
    <label>Patente <input type="text" name="patente" maxlength="20" value="<?= e($amb['patente']) ?>"></label>
    <label>Modelo <input type="text" name="modelo" maxlength="100" value="<?= e($amb['modelo']) ?>"></label>
    <label>Año <input type="number" name="anio" value="<?= e($amb['anio']) ?>"></label>
    <label>Estado
        <select name="estado"><?php opciones(ESTADOS_AMB, $amb['estado']); ?></select>
    </label>
    <button class="boton">Guardar</button>
</form>
<?php include 'includes/pie.php'; ?>
