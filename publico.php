<?php
// Página pública para los pacientes (no pide login)
require 'includes/base.php';

$buscar = trim($_GET['buscar'] ?? '');
// addcslashes para que no cuenten los % y _ del buscador
$like = '%' . addcslashes($buscar, '%_\\') . '%';

// si "buscar" está vacío salen todos los documentos
$documentos = consulta("SELECT id_documento, titulo, descripcion, categoria FROM documentos
                        WHERE (?='' OR titulo LIKE ? OR descripcion LIKE ?)
                        ORDER BY categoria, titulo", "sss", $buscar, $like, $like);

// agrupados por categoría
$por_categoria = [];
foreach ($documentos as $doc) {
    $por_categoria[$doc['categoria']][] = $doc;
}

// encuestas activas que tengan al menos una pregunta
$encuestas = consulta("SELECT e.id_encuesta, e.titulo, e.descripcion FROM encuestas e
                       WHERE e.estado='Activa' AND EXISTS (SELECT 1 FROM preguntas p WHERE p.id_encuesta=e.id_encuesta)
                       ORDER BY e.titulo");

$titulo = 'Documentación para pacientes';
include 'includes/cabecera.php';
?>
<h1>Documentación para pacientes</h1>
<p>Aquí puede consultar y descargar la información que antes se entregaba impresa.</p>
<form method="get">
    <label>Buscar un documento <input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Ej: ecocardiograma"></label>
    <button class="boton">Buscar</button>
</form>
<?php if (count($por_categoria) == 0) { ?>
<p>No hay documentación para mostrar.</p>
<?php } ?>
<?php foreach ($por_categoria as $categoria => $docs) { ?>
<h2><?= e($categoria) ?></h2>
<?php foreach ($docs as $doc) { ?>
<div class="tarjeta">
    <div><h3><?= e($doc['titulo']) ?></h3><p><?= e($doc['descripcion']) ?></p></div>
    <a class="boton" href="pdf.php?id=<?= e($doc['id_documento']) ?>">Ver documento</a>
</div>
<?php } ?>
<?php } ?>
<?php if (count($encuestas) > 0) { ?>
<h2>Encuestas de satisfacción</h2>
<?php foreach ($encuestas as $enc) { ?>
<div class="tarjeta">
    <div><h3><?= e($enc['titulo']) ?></h3><p><?= e($enc['descripcion']) ?></p></div>
    <a class="boton" href="encuesta.php?id_encuesta=<?= e($enc['id_encuesta']) ?>">Responder</a>
</div>
<?php } ?>
<?php } ?>
<?php include 'includes/pie.php'; ?>
