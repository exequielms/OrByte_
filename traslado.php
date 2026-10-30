<?php
// Seguimiento de un traslado: datos, historial y cambio de estado
require 'includes/base.php';
entrar(TRANSP);

$id = (int)($_GET['id'] ?? 0);
$t = buscar_uno("SELECT t.*, a.patente, a.modelo, CONCAT(p.nombre,' ',p.apellido) AS conductor, CONCAT(c.nombre,' ',c.apellido) AS copiloto,
                        r.nombre AS ruta, CONCAT(u.nombre,' ',u.apellido) AS usuario
                 FROM traslados t
                 JOIN ambulancias a ON a.id_ambulancia=t.id_ambulancia
                 JOIN personal p ON p.id_personal=t.id_conductor
                 LEFT JOIN personal c ON c.id_personal=t.id_copiloto
                 LEFT JOIN rutas r ON r.id_ruta=t.id_ruta
                 JOIN usuarios u ON u.id_usuario=t.id_usuario
                 WHERE t.id_traslado=?", "i", $id);
if (!$t) {
    aviso("No se encontró el traslado.");
    ir('traslados.php');
}

// fecha y hora, o un guion si todavía no hay
function formato_hora($valor) {
    if ($valor) {
        return date('d/m/Y H:i', strtotime($valor));
    }
    return '-';
}

$copiloto = $t['copiloto'];
if (!$copiloto) {
    $copiloto = 'Sin copiloto';
}
$ruta = $t['ruta'];
if (!$ruta) {
    $ruta = 'Sin ruta predefinida';
}
$observaciones = $t['observaciones'];
if (!$observaciones) {
    $observaciones = '-';
}

// tabla de arriba: título => valor
$ficha = [
    'Fecha' => date('d/m/Y', strtotime($t['fecha_traslado'])),
    'Ambulancia' => $t['patente'] . ' - ' . $t['modelo'],
    'Conductor' => $t['conductor'],
    'Copiloto' => $copiloto,
    'Se traslada' => $t['tipo_elemento'] . ': ' . $t['paciente_elemento'],
    'Origen' => $t['punto_origen'],
    'Destino' => $t['destino'],
    'Ruta' => $ruta,
    'Hora de salida' => formato_hora($t['hora_salida']),
    'Llegada estimada' => formato_hora($t['hora_estimada_llegada']),
    'Llegada efectiva' => formato_hora($t['hora_efectiva_llegada']),
    'Hora de regreso' => formato_hora($t['hora_regreso']),
    'Observaciones' => $observaciones,
    'Registrado por' => $t['usuario'],
];

$historial = consulta("SELECT DATE_FORMAT(s.fecha_hora, '%d/%m/%Y %H:%i') AS hora, s.estado,
                              IF(IFNULL(s.comentario, '') = '', '-', s.comentario) AS comentario, CONCAT(u.nombre,' ',u.apellido) AS usuario
                       FROM seguimiento s JOIN usuarios u ON u.id_usuario=s.id_usuario
                       WHERE s.id_traslado=? ORDER BY s.fecha_hora, s.id_seguimiento", "i", $id);

// a qué estados puede pasar ahora
$siguientes = TRANSICIONES[$t['estado']];

$titulo = 'Seguimiento';
include 'includes/cabecera.php';
?>
<h1>Seguimiento del traslado</h1>
<p><a href="traslados.php">Volver a traslados</a></p>
<table class="tabla">
    <tr><th>Estado</th><td><span class="estado estado-<?= e(clase_estado($t['estado'])) ?>"><?= e($t['estado']) ?></span></td></tr>
    <?php foreach ($ficha as $nombre => $valor) { ?>
    <tr><th><?= e($nombre) ?></th><td><?= e($valor) ?></td></tr>
    <?php } ?>
</table>

<h2>Historial</h2>
<table class="tabla">
    <tr><th>Fecha y hora</th><th>Estado</th><th>Comentario</th><th>Usuario</th></tr>
    <?php foreach ($historial as $h) { ?>
    <tr><td><?= e($h['hora']) ?></td><td><span class="estado estado-<?= e(clase_estado($h['estado'])) ?>"><?= e($h['estado']) ?></span></td><td><?= e($h['comentario']) ?></td><td><?= e($h['usuario']) ?></td></tr>
    <?php } ?>
</table>

<h2>Cambiar estado</h2>
<?php if (count($siguientes) > 0) { ?>
<form method="post" action="estado_traslado.php">
    <?php csrf(); ?>
    <input type="hidden" name="id" value="<?= e($id) ?>">
    <label>Nuevo estado <select name="estado"><?php opciones($siguientes); ?></select></label>
    <label>Comentario (opcional) <textarea name="comentario" maxlength="2000"></textarea></label>
    <button class="boton">Cambiar estado</button>
</form>
<?php } else { ?>
<p>El traslado está cerrado.</p>
<?php } ?>
<?php include 'includes/pie.php'; ?>
