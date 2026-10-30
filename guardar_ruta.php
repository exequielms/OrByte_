<?php
// Guarda, edita o borra una ruta (solo recibe POST)
require 'includes/base.php';
entrar(TRANSP);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('rutas.php');
}

// Borrar
if (isset($_POST['borrar'])) {
    $id_borrar = (int)$_POST['borrar'];
    if (existe("SELECT 1 FROM traslados WHERE id_ruta=?", "i", $id_borrar)) {
        aviso("No se puede eliminar: la ruta ya fue usada en traslados.");
    } else {
        consulta("DELETE FROM rutas WHERE id_ruta=?", "i", $id_borrar);
        aviso("Ruta eliminada.");
    }
    ir('rutas.php');
}

// Datos
$id = (int)($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$origen = trim($_POST['origen'] ?? '');
$destino = trim($_POST['destino'] ?? '');
$km = (float)($_POST['distancia_km'] ?? 0);
$minutos = (int)($_POST['tiempo_estimado_min'] ?? 0);

// Validar y guardar
if ($nombre == '' || $origen == '' || $destino == '' || $km <= 0 || $minutos <= 0) {
    aviso("Complete todos los campos; distancia y tiempo deben ser mayores a cero.");
} elseif ($id) {
    consulta("UPDATE rutas SET nombre=?, origen=?, destino=?, distancia_km=?, tiempo_estimado_min=? WHERE id_ruta=?",
             "sssdii", $nombre, $origen, $destino, $km, $minutos, $id);
    aviso("Ruta actualizada.");
} else {
    consulta("INSERT INTO rutas (nombre, origen, destino, distancia_km, tiempo_estimado_min) VALUES (?,?,?,?,?)",
             "sssdi", $nombre, $origen, $destino, $km, $minutos);
    aviso("Ruta guardada.");
}
ir('rutas.php');
