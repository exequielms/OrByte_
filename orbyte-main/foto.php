<?php
// Muestra la foto de perfil del usuario que inició sesión
require 'includes/base.php';
entrar(ROLES_TODOS, true);

$archivo = $_SESSION['usuario']['foto'] ?? '';
$ruta = '';
if ($archivo) {
    $ruta = CARPETA_FOTOS . '/' . basename($archivo);
}
if (!$archivo || !is_file($ruta)) {
    http_response_code(404);
    exit;
}

$tipos = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
header('Content-Type: ' . ($tipos[$extension] ?? 'application/octet-stream'));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
