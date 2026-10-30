<?php
// Personal: conductores, enfermeras y auxiliares
require 'includes/base.php';
entrar(TRANSP);

$lista = consulta("SELECT *, DATE_FORMAT(fecha_ingreso, '%d/%m/%Y') AS ingreso FROM personal ORDER BY apellido, nombre");

// ?id=... para editar, sin id es una persona nueva
$persona = buscar_uno("SELECT * FROM personal WHERE id_personal=?", "i", (int)($_GET['id'] ?? 0));
$editando = ($persona !== null);
if (!$editando) {
    $persona = ['id_personal' => '', 'nombre' => '', 'apellido' => '', 'documento' => '', 'cargo' => '', 'telefono' => '', 'fecha_ingreso' => ''];
}

$titulo = 'Personal';
include 'includes/cabecera.php';
?>
<h1>Gestión de personal</h1>
<table class="tabla">
    <tr><th>Nombre</th><th>Documento</th><th>Cargo</th><th>Teléfono</th><th>Ingreso</th><th>Acciones</th></tr>
    <?php foreach ($lista as $p) { ?>
    <tr>
        <td><?= e($p['nombre']) ?> <?= e($p['apellido']) ?></td><td><?= e($p['documento']) ?></td><td><?= e($p['cargo']) ?></td><td><?= e($p['telefono']) ?></td><td><?= e($p['ingreso']) ?></td>
        <td>
            <a href="?id=<?= e($p['id_personal']) ?>">Editar</a> |
            <form method="post" action="guardar_personal.php" class="inline" data-confirmar="¿Seguro que quiere eliminar?">
                <?php csrf(); ?><input type="hidden" name="borrar" value="<?= e($p['id_personal']) ?>"><button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="6">No hay personal cargado.</td></tr><?php } ?>
</table>

<h2><?= $editando ? 'Editar' : 'Nuevo' ?> personal</h2>
<form method="post" action="guardar_personal.php">
    <?php csrf(); ?>
    <input type="hidden" name="id" value="<?= e($persona['id_personal']) ?>">
    <label>Nombre <input type="text" name="nombre" maxlength="100" value="<?= e($persona['nombre']) ?>"></label>
    <label>Apellido <input type="text" name="apellido" maxlength="100" value="<?= e($persona['apellido']) ?>"></label>
    <label>Documento <input type="text" name="documento" maxlength="20" value="<?= e($persona['documento']) ?>"></label>
    <label>Cargo
        <select name="cargo"><?php opciones(CARGOS, $persona['cargo']); ?></select>
    </label>
    <label>Teléfono <input type="text" name="telefono" maxlength="20" value="<?= e($persona['telefono']) ?>"></label>
    <label>Fecha de ingreso <input type="date" name="fecha_ingreso" value="<?= e($persona['fecha_ingreso']) ?>"></label>
    <button class="boton">Guardar</button>
</form>
<?php include 'includes/pie.php'; ?>
