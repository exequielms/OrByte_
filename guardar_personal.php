<?php
// Guarda, edita o borra una persona del personal (solo recibe POST)
require 'includes/base.php';
entrar(TRANSP);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('personal.php');
}

// borrar
if (isset($_POST['borrar'])) {
    $id_borrar = (int)$_POST['borrar'];
    if (existe("SELECT 1 FROM traslados WHERE id_conductor=? OR id_copiloto=?", "ii", $id_borrar, $id_borrar)) {
        aviso("No se puede eliminar: la persona participó en traslados.");
    } else {
        consulta("DELETE FROM personal WHERE id_personal=?", "i", $id_borrar);
        aviso("Persona eliminada.");
    }
    ir('personal.php');
}

// datos del formulario
$id = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$documento = trim($_POST['documento'] ?? '');
$cargo = $_POST['cargo'] ?? '';
$telefono = trim($_POST['telefono'] ?? '');
$fecha = $_POST['fecha_ingreso'] ?? '';
$fecha_numero = strtotime($fecha);   // false si la fecha no es válida

// validaciones y guardado
if ($nombre == '' || $apellido == '' || $documento == '' || !in_array($cargo, CARGOS) || !$fecha_numero || $fecha_numero > time()) {
    aviso("Complete nombre, apellido y documento, elija un cargo y una fecha de ingreso que no sea futura.");
} elseif (existe("SELECT 1 FROM personal WHERE documento=? AND id_personal<>?", "si", $documento, $id)) {
    aviso("Ya hay una persona con ese documento.");
} elseif ($id) {
    consulta("UPDATE personal SET nombre=?, apellido=?, documento=?, cargo=?, telefono=?, fecha_ingreso=? WHERE id_personal=?",
             "ssssssi", $nombre, $apellido, $documento, $cargo, $telefono, $fecha, $id);
    aviso("Personal actualizado.");
} else {
    consulta("INSERT INTO personal (nombre, apellido, documento, cargo, telefono, fecha_ingreso) VALUES (?,?,?,?,?,?)",
             "ssssss", $nombre, $apellido, $documento, $cargo, $telefono, $fecha);
    aviso("Personal guardado.");
}
ir('personal.php');
