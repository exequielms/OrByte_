<?php
// manda el PDF de un documento (es público, no pide login). basename() para que no puedan cambiar la ruta
require 'includes/base.php';

$id = (int)($_GET['id'] ?? 0);
$doc = buscar_uno("SELECT archivo FROM documentos WHERE id_documento=?", "i", $id);

$ruta = '';
if ($doc) {
    $ruta = CARPETA_PDF . '/' . basename($doc['archivo']);
}
if (!$doc || !is_file($ruta)) {
    http_response_code(404);
    die('Documento no encontrado');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="documento.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
