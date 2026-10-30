<?php
// Guarda, edita o borra una ambulancia (solo recibe POST)
require 'includes/base.php';
entrar(TRANSP);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('ambulancias.php');
}

// borrar
if (isset($_POST['borrar'])) {
    $id_borrar = (int)$_POST['borrar'];
    // una ambulancia con traslados no se borra: es historial del hospital
    if (existe("SELECT 1 FROM traslados WHERE id_ambulancia=?", "i", $id_borrar)) {
        aviso("No se puede eliminar: tiene traslados. Cámbiele el estado a Mantenimiento.");
    } else {
        consulta("DELETE FROM ambulancias WHERE id_ambulancia=?", "i", $id_borrar);
        aviso("Ambulancia eliminada.");
    }
    ir('ambulancias.php');
}

// datos del form
$id = (int)($_POST['id'] ?? 0);
$patente = trim($_POST['patente'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$anio = (int)($_POST['anio'] ?? 0);
$estado = $_POST['estado'] ?? '';

// validar y guardar
if ($patente == '' || $modelo == '' || $anio < 1990 || $anio > date('Y') + 1 || !in_array($estado, ESTADOS_AMB)) {
    aviso("Revise los datos: patente, modelo, año (desde 1990) y estado.");
} elseif (existe("SELECT 1 FROM ambulancias WHERE patente=? AND id_ambulancia<>?", "si", $patente, $id)) {
    aviso("Ya hay una ambulancia con esa patente.");
} elseif ($id) {
    consulta("UPDATE ambulancias SET patente=?, modelo=?, anio=?, estado=? WHERE id_ambulancia=?", "ssisi", $patente, $modelo, $anio, $estado, $id);
    aviso("Ambulancia actualizada.");
} else {
    consulta("INSERT INTO ambulancias (patente, modelo, anio, estado) VALUES (?,?,?,?)", "ssis", $patente, $modelo, $anio, $estado);
    aviso("Ambulancia guardada.");
}
ir('ambulancias.php');
