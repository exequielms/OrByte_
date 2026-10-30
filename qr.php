<?php
// Genera el código QR de un documento, de una encuesta o de la página pública.
// el QR en sí lo dibuja assets/qr.js en el navegador
require 'includes/base.php';
entrar(DOCS);

$tipo = $_GET['tipo'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$nombre = '';
$detalle = '';
$ruta = '';
$volver = 'documentos.php';

if ($tipo == 'documento') {
    $doc = buscar_uno("SELECT titulo, categoria FROM documentos WHERE id_documento=?", "i", $id);
    if ($doc) {
        $nombre = $doc['titulo'];
        $detalle = $doc['categoria'];
        $ruta = "pdf.php?id=$id";
    }
} elseif ($tipo == 'encuesta') {
    $enc = buscar_uno("SELECT titulo FROM encuestas WHERE id_encuesta=?", "i", $id);
    if ($enc) {
        $nombre = $enc['titulo'];
        $detalle = 'Encuesta de satisfacción';
        $ruta = "encuesta.php?id_encuesta=$id";
        $volver = 'encuestas.php';
    }
} elseif ($tipo == 'publico') {
    $nombre = 'Documentación para pacientes';
    $detalle = 'Todos los documentos del hospital';
    $ruta = 'publico.php';
}

if ($ruta == '') {
    aviso('No se encontró lo que quiere generar.');
    ir('documentos.php');
}

// dirección del servidor, por ejemplo http://localhost/orbyte
$esquema = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
    $esquema = 'https';
}
$servidor = $_SERVER['HTTP_HOST'] ?? 'localhost';
$carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base = $esquema . '://' . $servidor . $carpeta;

// nombre del PNG que se descarga
$archivo = 'qr-' . $tipo;
if ($id) {
    $archivo = $archivo . '-' . $id;
}

$titulo = 'Código QR';
include 'includes/cabecera.php';
?>
<h1>Código QR</h1>
<p class="no-imprimir"><a href="<?= e($volver) ?>">Volver</a></p>
<div class="qr-caja" id="qr-caja" data-ruta="<?= e($ruta) ?>" data-archivo="<?= e($archivo) ?>">
    <h2><?= e($nombre) ?></h2>
    <p><?= e($detalle) ?></p>
    <div id="qr" class="qr-imagen"></div>
    <p class="qr-leyenda">Escanee el código con la cámara de su celular</p>
    <small id="qr-direccion"></small>
</div>
<div class="caja no-imprimir">
    <label>Dirección del servidor <input type="text" id="qr-base" value="<?= e($base) ?>"></label>
    <p class="nota" id="qr-aviso" hidden>Esta dirección solo funciona en esta computadora. Para que el celular del paciente pueda abrirla, escriba la dirección del servidor del hospital (por ejemplo http://192.168.1.20/orbyte).</p>
    <button type="button" class="boton" id="qr-imprimir">Imprimir</button>
    <button type="button" class="boton secundario" id="qr-descargar">Descargar imagen (PNG)</button>
</div>
<script src="assets/qr.js"></script>
<?php include 'includes/pie.php'; ?>
