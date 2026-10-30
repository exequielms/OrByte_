<?php
// Rutas
require 'includes/base.php';
entrar(TRANSP);

$lista = consulta("SELECT * FROM rutas ORDER BY nombre");

// ?id=... para editar
$ruta = buscar_uno("SELECT * FROM rutas WHERE id_ruta=?", "i", (int)($_GET['id'] ?? 0));
$editando = ($ruta !== null);
if (!$editando) {
    $ruta = ['id_ruta' => '', 'nombre' => '', 'origen' => '', 'destino' => '', 'distancia_km' => '', 'tiempo_estimado_min' => ''];
}

$titulo = 'Rutas';
include 'includes/cabecera.php';
?>
<h1>Gestión de rutas</h1>
<table class="tabla">
    <tr><th>Nombre</th><th>Origen</th><th>Destino</th><th>Km</th><th>Minutos</th><th>Acciones</th></tr>
    <?php foreach ($lista as $r) { ?>
    <tr>
        <td><?= e($r['nombre']) ?></td><td><?= e($r['origen']) ?></td><td><?= e($r['destino']) ?></td><td><?= e($r['distancia_km']) ?></td><td><?= e($r['tiempo_estimado_min']) ?></td>
        <td>
            <a href="?id=<?= e($r['id_ruta']) ?>">Editar</a> |
            <form method="post" action="guardar_ruta.php" class="inline" data-confirmar="¿Seguro que quiere eliminar?">
                <?php csrf(); ?><input type="hidden" name="borrar" value="<?= e($r['id_ruta']) ?>"><button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="6">No hay rutas cargadas.</td></tr><?php } ?>
</table>

<h2><?= $editando ? 'Editar' : 'Nueva' ?> ruta</h2>
<form method="post" action="guardar_ruta.php">
    <?php csrf(); ?>
    <input type="hidden" name="id" value="<?= e($ruta['id_ruta']) ?>">
    <label>Nombre <input type="text" name="nombre" maxlength="200" value="<?= e($ruta['nombre']) ?>"></label>
    <label>Origen <input type="text" name="origen" maxlength="200" value="<?= e($ruta['origen']) ?>"></label>
    <label>Destino <input type="text" name="destino" maxlength="200" value="<?= e($ruta['destino']) ?>"></label>
    <label>Distancia (km) <input type="number" step="0.01" name="distancia_km" value="<?= e($ruta['distancia_km']) ?>"></label>
    <label>Tiempo estimado (min) <input type="number" name="tiempo_estimado_min" value="<?= e($ruta['tiempo_estimado_min']) ?>"></label>
    <button class="boton">Guardar</button>
</form>
<?php include 'includes/pie.php'; ?>
