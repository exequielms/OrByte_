<?php
// Encuestas: lista, alta y armado de preguntas
require 'includes/base.php';
entrar(DOCS);

$lista = consulta("SELECT e.id_encuesta, e.titulo, DATE_FORMAT(e.fecha_creacion, '%d/%m/%Y') AS fecha, e.estado,
                          (SELECT COUNT(*) FROM preguntas p WHERE p.id_encuesta=e.id_encuesta) AS n_preguntas,
                          (SELECT COUNT(*) FROM respuestas r WHERE r.id_encuesta=e.id_encuesta) AS n_respuestas
                   FROM encuestas e ORDER BY e.fecha_creacion DESC, e.id_encuesta DESC");

// la encuesta que se está editando (?id=...)
$sel = buscar_uno("SELECT * FROM encuestas WHERE id_encuesta=?", "i", (int)($_GET['id'] ?? 0));
$preguntas = [];
$total_respuestas = 0;
if ($sel) {
    $preguntas = consulta("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta=? ORDER BY id_pregunta", "i", $sel['id_encuesta']);
    foreach ($preguntas as $i => $p) {
        $preguntas[$i]['opciones'] = [];
        if ($p['tipo'] == 'cerrada') {
            $preguntas[$i]['opciones'] = consulta("SELECT texto FROM opciones_respuesta WHERE id_pregunta=? ORDER BY id_opcion", "i", $p['id_pregunta']);
        }
    }
    $fila = buscar_uno("SELECT COUNT(*) AS n FROM respuestas WHERE id_encuesta=?", "i", $sel['id_encuesta']);
    $total_respuestas = $fila['n'];
}

$titulo = 'Encuestas';
include 'includes/cabecera.php';
?>
<h1>Gestión de encuestas</h1>
<table class="tabla">
    <tr><th>Título</th><th>Fecha</th><th>Estado</th><th>Preguntas</th><th>Respuestas</th><th>Acciones</th></tr>
    <?php foreach ($lista as $enc) { ?>
    <tr>
        <td><?= e($enc['titulo']) ?></td><td><?= e($enc['fecha']) ?></td>
        <td><span class="estado estado-<?= e($enc['estado']) ?>"><?= e($enc['estado']) ?></span></td>
        <td><?= e($enc['n_preguntas']) ?></td><td><?= e($enc['n_respuestas']) ?></td>
        <td>
            <a href="?id=<?= e($enc['id_encuesta']) ?>#preguntas">Editar</a> |
            <a href="indicadores.php?id=<?= e($enc['id_encuesta']) ?>">Resultados</a> |
            <a href="qr.php?tipo=encuesta&id=<?= e($enc['id_encuesta']) ?>">QR</a> |
            <form method="post" action="guardar_encuesta.php" class="inline">
                <?php csrf(); ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_encuesta" value="<?= e($enc['id_encuesta']) ?>">
                <button class="enlace"><?= $enc['estado'] == 'Activa' ? 'Desactivar' : 'Activar' ?></button>
            </form> |
            <form method="post" action="guardar_encuesta.php" class="inline">
                <?php csrf(); ?><input type="hidden" name="accion" value="clonar"><input type="hidden" name="id_encuesta" value="<?= e($enc['id_encuesta']) ?>">
                <button class="enlace">Duplicar</button>
            </form> |
            <form method="post" action="guardar_encuesta.php" class="inline" data-confirmar="Se borra la encuesta con todas sus preguntas y respuestas. ¿Seguro?">
                <?php csrf(); ?><input type="hidden" name="accion" value="borrar"><input type="hidden" name="id_encuesta" value="<?= e($enc['id_encuesta']) ?>">
                <button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="6">No hay encuestas.</td></tr><?php } ?>
</table>

<h2>Nueva encuesta</h2>
<form method="post" action="guardar_encuesta.php">
    <?php csrf(); ?><input type="hidden" name="accion" value="nueva">
    <label>Título <input type="text" name="titulo" maxlength="200" required placeholder="Ej: Satisfacción con la atención en Nefrología"></label>
    <label>Descripción (la ve el paciente) <textarea name="descripcion" maxlength="2000" placeholder="Ej: Su opinión nos ayuda a mejorar."></textarea></label>
    <button class="boton">Crear y agregar preguntas</button>
</form>

<?php if ($sel) { ?>
<h2 id="preguntas">Encuesta: <?= e($sel['titulo']) ?></h2>
<p>
    <span class="estado estado-<?= e($sel['estado']) ?>"><?= e($sel['estado']) ?></span>
    <small>&nbsp;<?= e($total_respuestas) ?> respuestas recibidas</small> |
    <a href="encuesta.php?id_encuesta=<?= e($sel['id_encuesta']) ?>" target="_blank">Verla como la ve el paciente</a> |
    <a href="qr.php?tipo=encuesta&id=<?= e($sel['id_encuesta']) ?>">Código QR</a> |
    <a href="indicadores.php?id=<?= e($sel['id_encuesta']) ?>">Resultados</a>
</p>
<?php if ($sel['estado'] != 'Activa') { ?><div class="nota">Esta encuesta está inactiva: los pacientes no la ven. Actívela desde la lista cuando termine de armarla.</div><?php } ?>
<?php if ($total_respuestas > 0) { ?><div class="nota">Esta encuesta ya tiene respuestas. Si borra una pregunta, se borran también sus respuestas. Para hacer cambios grandes conviene duplicarla.</div><?php } ?>

<h3>Datos de la encuesta</h3>
<form method="post" action="guardar_encuesta.php">
    <?php csrf(); ?><input type="hidden" name="accion" value="editar"><input type="hidden" name="id_encuesta" value="<?= e($sel['id_encuesta']) ?>">
    <label>Título <input type="text" name="titulo" maxlength="200" required value="<?= e($sel['titulo']) ?>"></label>
    <label>Descripción <textarea name="descripcion" maxlength="2000"><?= e($sel['descripcion']) ?></textarea></label>
    <button class="boton">Guardar datos</button>
</form>

<h3>Preguntas (<?= count($preguntas) ?>)</h3>
<table class="tabla">
    <tr><th>#</th><th>Pregunta</th><th>Tipo</th><th>Opciones</th><th></th></tr>
    <?php foreach ($preguntas as $i => $p) { ?>
    <tr>
        <td><?= $i + 1 ?></td><td><?= e($p['texto']) ?></td>
        <td><?= $p['tipo'] == 'cerrada' ? 'Elegir una opción' : 'Texto libre' ?></td>
        <td>
            <?php if ($p['tipo'] == 'cerrada') { ?>
            <ul><?php foreach ($p['opciones'] as $o) { ?><li><?= e($o['texto']) ?></li><?php } ?></ul>
            <?php } else { ?>-<?php } ?>
        </td>
        <td>
            <form method="post" action="guardar_encuesta.php" class="inline" data-confirmar="Se borran también sus respuestas. ¿Seguro?">
                <?php csrf(); ?><input type="hidden" name="accion" value="borrar_pregunta"><input type="hidden" name="id_encuesta" value="<?= e($sel['id_encuesta']) ?>">
                <input type="hidden" name="id_pregunta" value="<?= e($p['id_pregunta']) ?>"><button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($preguntas) == 0) { ?><tr><td colspan="5">Todavía no hay preguntas. Agregue la primera con el formulario de abajo.</td></tr><?php } ?>
</table>

<h3>Agregar pregunta</h3>
<form method="post" action="guardar_encuesta.php">
    <?php csrf(); ?><input type="hidden" name="accion" value="pregunta"><input type="hidden" name="id_encuesta" value="<?= e($sel['id_encuesta']) ?>">
    <label>Pregunta <input type="text" name="texto" maxlength="1000" required placeholder="Ej: ¿Cómo califica la atención recibida?"></label>
    <label>Tipo de pregunta
        <select name="tipo" id="tipo-pregunta">
            <option value="escala">Escala de satisfacción (de Muy mala a Muy buena)</option>
            <option value="si_no">Sí / No</option>
            <option value="unica">Elegir una opción (yo escribo las opciones)</option>
            <option value="abierta">Texto libre (el paciente escribe)</option>
        </select>
    </label>
    <p><small id="aviso-tipo">Se crean solas las opciones: Muy mala, Mala, Regular, Buena y Muy buena.</small></p>
    <div id="bloque-opciones">
        <p><strong>Opciones de respuesta</strong> <small>(mínimo 2, solo para "Elegir una opción")</small></p>
        <div id="lista-opciones">
            <input type="text" name="opciones[]" maxlength="200" placeholder="Opción 1">
            <input type="text" name="opciones[]" maxlength="200" placeholder="Opción 2">
            <input type="text" name="opciones[]" maxlength="200" placeholder="Opción 3">
        </div>
        <p><button type="button" class="boton secundario" id="agregar-opcion">+ Agregar otra opción</button></p>
    </div>
    <button class="boton">Guardar pregunta</button>
</form>
<?php } ?>
<?php include 'includes/pie.php'; ?>
