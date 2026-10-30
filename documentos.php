<?php
// Documentos: lista con filtros y formulario para subir o editar
require 'includes/base.php';
entrar(DOCS);

// filtros
$buscar = trim($_GET['buscar'] ?? '');
$like = '%' . addcslashes($buscar, '%_\\') . '%';
$categoria = '';
if (in_array($_GET['categoria'] ?? '', CATEGORIAS)) {
    $categoria = $_GET['categoria'];
}
$hay_filtro = ($buscar !== '' || $categoria !== '');

$lista = consulta("SELECT d.id_documento, d.titulo, d.descripcion, d.categoria,
                          DATE_FORMAT(d.fecha_alta, '%d/%m/%Y') AS fecha, CONCAT(u.nombre,' ',u.apellido) AS usuario
                   FROM documentos d JOIN usuarios u ON u.id_usuario=d.id_usuario
                   WHERE (?='' OR d.categoria=?) AND (?='' OR d.titulo LIKE ? OR d.descripcion LIKE ?)
                   ORDER BY d.fecha_alta DESC, d.id_documento DESC", "sssss", $categoria, $categoria, $buscar, $like, $like);

// con ?id=... se edita, sin id queda vacío para subir uno nuevo
$doc = buscar_uno("SELECT * FROM documentos WHERE id_documento=?", "i", (int)($_GET['id'] ?? 0));
$editando = ($doc !== null);
if (!$editando) {
    $doc = ['id_documento' => '', 'titulo' => '', 'descripcion' => '', 'categoria' => ''];
}

$titulo = 'Documentos';
include 'includes/cabecera.php';
?>
<h1>Gestión de documentos</h1>
<p>
    <a href="publico.php" target="_blank">Ver la página pública</a> |
    <a href="qr.php?tipo=publico">Código QR de la página pública (todos los documentos)</a>
</p>

<form method="get" class="filtros">
    <label>Buscar <input type="search" name="buscar" value="<?= e($buscar) ?>" placeholder="Título o descripción"></label>
    <label>Categoría
        <select name="categoria"><option value="">Todas</option><?php opciones(CATEGORIAS, $categoria); ?></select>
    </label>
    <button class="boton">Buscar</button>
    <?php if ($hay_filtro) { ?><a class="boton secundario" href="documentos.php">Quitar filtros</a><?php } ?>
</form>

<p><small>Documentos encontrados: <?= count($lista) ?></small></p>
<table class="tabla">
    <tr><th>Documento</th><th>Categoría</th><th>Fecha</th><th>Cargado por</th><th>Acciones</th></tr>
    <?php foreach ($lista as $d) { ?>
    <tr>
        <td><strong><?= e($d['titulo']) ?></strong><br><small><?= e($d['descripcion']) ?></small></td>
        <td><?= e($d['categoria']) ?></td><td><?= e($d['fecha']) ?></td><td><?= e($d['usuario']) ?></td>
        <td>
            <a href="pdf.php?id=<?= e($d['id_documento']) ?>" target="_blank">Abrir PDF</a> |
            <a href="qr.php?tipo=documento&id=<?= e($d['id_documento']) ?>">QR</a> |
            <a href="?id=<?= e($d['id_documento']) ?>#formulario">Editar</a> |
            <form method="post" action="guardar_documento.php" class="inline" data-confirmar="¿Seguro que quiere eliminar este documento y su archivo?">
                <?php csrf(); ?><input type="hidden" name="borrar" value="<?= e($d['id_documento']) ?>"><button class="eliminar">Eliminar</button>
            </form>
        </td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="5">No hay documentos para mostrar.</td></tr><?php } ?>
</table>

<h2 id="formulario"><?= $editando ? 'Editar' : 'Subir nuevo' ?> documento</h2>
<form method="post" action="guardar_documento.php" enctype="multipart/form-data">
    <?php csrf(); ?>
    <input type="hidden" name="id" value="<?= e($doc['id_documento']) ?>">
    <label>Título <input type="text" name="titulo" maxlength="200" required value="<?= e($doc['titulo']) ?>"></label>
    <label>Descripción (la ve el paciente) <textarea name="descripcion" maxlength="2000"><?= e($doc['descripcion']) ?></textarea></label>
    <label>Categoría <select name="categoria"><?php opciones(CATEGORIAS, $doc['categoria']); ?></select></label>
    <label>Archivo PDF (hasta 5 MB) <input type="file" name="archivo" accept="application/pdf,.pdf"></label>
    <?php if ($editando) { ?>
    <p><small>Si no elige un archivo, se mantiene el PDF actual. <a href="pdf.php?id=<?= e($doc['id_documento']) ?>" target="_blank">Ver PDF actual</a></small></p>
    <?php } ?>
    <button class="boton">Guardar</button>
    <?php if ($editando) { ?><a class="boton secundario" href="documentos.php">Cancelar</a><?php } ?>
</form>
<?php include 'includes/pie.php'; ?>
