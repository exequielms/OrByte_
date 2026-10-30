<?php
// Encuesta pública y anónima para el paciente
require 'includes/base.php';

$gracias = isset($_GET['gracias']);
$id = (int)($_GET['id_encuesta'] ?? 0);

// busca la encuesta pedida; si no piden ninguna (id 0) toma la primera activa que tenga preguntas
$encuesta = buscar_uno("SELECT * FROM encuestas
                        WHERE estado='Activa' AND (id_encuesta=? OR ?=0)
                        AND EXISTS (SELECT 1 FROM preguntas p WHERE p.id_encuesta=encuestas.id_encuesta)
                        ORDER BY id_encuesta LIMIT 1", "ii", $id, $id);

// preguntas con sus opciones
$preguntas = [];
if ($encuesta) {
    $preguntas = consulta("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta=? ORDER BY id_pregunta", "i", $encuesta['id_encuesta']);
    foreach ($preguntas as $i => $p) {
        $preguntas[$i]['opciones'] = [];
        if ($p['tipo'] == 'cerrada') {
            $preguntas[$i]['opciones'] = consulta("SELECT id_opcion, texto FROM opciones_respuesta WHERE id_pregunta=? ORDER BY id_opcion", "i", $p['id_pregunta']);
        }
    }
}

$titulo = 'Encuesta';
include 'includes/cabecera.php';
?>
<?php if ($gracias) { ?>
<h1>Gracias por responder</h1>
<p>Su opinión nos ayuda a mejorar la atención del hospital.</p>
<p><a href="publico.php">Volver a la documentación</a></p>
<?php } elseif (!$encuesta) { ?>
<h1>Encuesta</h1>
<p>No hay encuestas disponibles por el momento.</p>
<p><a href="publico.php">Volver a la documentación</a></p>
<?php } else { ?>
<h1><?= e($encuesta['titulo']) ?></h1>
<p><?= e($encuesta['descripcion']) ?></p>
<p><small>La encuesta es anónima. Puede dejar en blanco las preguntas que no quiera contestar.</small></p>
<form method="post" action="guardar_respuesta.php">
    <?php csrf(); ?><input type="hidden" name="id_encuesta" value="<?= e($encuesta['id_encuesta']) ?>">
    <?php foreach ($preguntas as $i => $p) { ?>
    <fieldset class="pregunta">
        <legend><?= $i + 1 ?>. <?= e($p['texto']) ?></legend>
        <?php if ($p['tipo'] == 'cerrada') { ?>
            <?php foreach ($p['opciones'] as $o) { ?>
            <label class="opcion"><input type="radio" name="opcion[<?= e($p['id_pregunta']) ?>]" value="<?= e($o['id_opcion']) ?>"> <?= e($o['texto']) ?></label>
            <?php } ?>
        <?php } else { ?>
        <textarea name="texto[<?= e($p['id_pregunta']) ?>]" maxlength="2000" placeholder="Escriba aquí su respuesta"></textarea>
        <?php } ?>
    </fieldset>
    <?php } ?>
    <button class="boton">Enviar respuestas</button>
</form>
<?php } ?>
<?php include 'includes/pie.php'; ?>
