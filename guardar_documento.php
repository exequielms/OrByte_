<?php
// Guarda, edita o borra un documento (solo recibe POST)
require 'includes/base.php';
entrar(DOCS);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('documentos.php');
}

// borrar el documento (y también el PDF)
if (isset($_POST['borrar'])) {
    $id_borrar = (int)$_POST['borrar'];
    $viejo = buscar_uno("SELECT archivo FROM documentos WHERE id_documento=?", "i", $id_borrar);
    consulta("DELETE FROM documentos WHERE id_documento=?", "i", $id_borrar);
    if ($viejo) {
        @unlink(CARPETA_PDF . '/' . basename($viejo['archivo']));
    }
    aviso("Documento eliminado.");
    ir('documentos.php');
}

// datos del formulario
$id = (int)($_POST['id'] ?? 0);
$titulo_doc = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
$categoria = $_POST['categoria'] ?? '';
$archivo = $_FILES['archivo'] ?? ['error' => 4];   // error 4 = no se eligió ningún archivo
$subio_archivo = ($archivo['error'] != 4);

// validaciones
$error = '';
if ($titulo_doc == '' || mb_strlen($titulo_doc) > 200 || mb_strlen($descripcion) > 2000 || !in_array($categoria, CATEGORIAS)) {
    $error = "Complete el título (hasta 200 letras), la descripción y la categoría.";
} elseif ($id && !existe("SELECT 1 FROM documentos WHERE id_documento=?", "i", $id)) {
    $error = "El documento no existe.";
} elseif (!$id && !$subio_archivo) {
    $error = "Debe elegir un archivo PDF.";
} elseif ($subio_archivo && $archivo['error'] != 0) {
    $error = "No se pudo subir el archivo. Revise que no pase de 5 MB.";
} elseif ($subio_archivo) {
    // tiene que ser .pdf, pesar menos de 5 MB y empezar con %PDF-
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $firma = file_get_contents($archivo['tmp_name'], false, null, 0, 5);
    if ($extension != 'pdf' || $archivo['size'] > 5 * 1024 * 1024 || $firma != '%PDF-') {
        $error = "El archivo debe ser un PDF de hasta 5 MB.";
    }
}

if ($error != '') {
    aviso($error);
    if ($id) {
        ir("documentos.php?id=$id");
    }
    ir('documentos.php');
}

// el PDF se guarda con un nombre al azar
$nombre_nuevo = '';
if ($subio_archivo) {
    if (!is_dir(CARPETA_PDF)) {
        mkdir(CARPETA_PDF, 0755, true);
        file_put_contents(CARPETA_PDF . '/.htaccess', "Require all denied\n");   // que no se pueda entrar directo a los PDF
    }
    $nombre_nuevo = bin2hex(random_bytes(10)) . '.pdf';
    if (!move_uploaded_file($archivo['tmp_name'], CARPETA_PDF . '/' . $nombre_nuevo)) {
        aviso("No se pudo guardar el archivo en el servidor.");
        ir('documentos.php');
    }
}

// guardar en la base
if ($id && $nombre_nuevo != '') {
    // se edita y además se cambia el PDF: se borra el PDF anterior
    $viejo = buscar_uno("SELECT archivo FROM documentos WHERE id_documento=?", "i", $id);
    consulta("UPDATE documentos SET titulo=?, descripcion=?, categoria=?, archivo=? WHERE id_documento=?",
             "ssssi", $titulo_doc, $descripcion, $categoria, $nombre_nuevo, $id);
    if ($viejo) {
        @unlink(CARPETA_PDF . '/' . basename($viejo['archivo']));
    }
} elseif ($id) {
    // edición sin cambiar el PDF
    consulta("UPDATE documentos SET titulo=?, descripcion=?, categoria=? WHERE id_documento=?",
             "sssi", $titulo_doc, $descripcion, $categoria, $id);
} else {
    // documento nuevo
    consulta("INSERT INTO documentos (titulo, descripcion, archivo, categoria, fecha_alta, id_usuario) VALUES (?,?,?,?,NOW(),?)",
             "ssssi", $titulo_doc, $descripcion, $nombre_nuevo, $categoria, $_SESSION['usuario']['id']);
}
aviso("Documento guardado.");
ir('documentos.php');
