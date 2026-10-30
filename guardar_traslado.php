<?php
// Guarda un traslado nuevo (solo recibe POST)
require 'includes/base.php';
entrar(TRANSP);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('traslados.php');
}

// datos del formulario
$id_ambulancia = (int)($_POST['id_ambulancia'] ?? 0);
$id_conductor = (int)($_POST['id_conductor'] ?? 0);
$id_copiloto = (int)($_POST['id_copiloto'] ?? 0);   // opcional: 0 = sin copiloto
$id_ruta = (int)($_POST['id_ruta'] ?? 0);           // opcional: 0 = sin ruta
$tipo = $_POST['tipo_elemento'] ?? '';
$elemento = trim($_POST['paciente_elemento'] ?? '');
$origen = trim($_POST['punto_origen'] ?? '');
$destino = trim($_POST['destino'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');
$fecha = $_POST['fecha_traslado'] ?? '';

// sin copiloto y sin ruta se guardan como NULL
$copiloto_guardar = null;
if ($id_copiloto > 0) {
    $copiloto_guardar = $id_copiloto;
}
$ruta_guardar = null;
if ($id_ruta > 0) {
    $ruta_guardar = $id_ruta;
}

// validar, y que los ids existan en la base
if ($elemento == '' || $origen == '' || $destino == '' || !in_array($tipo, TIPOS) || !strtotime($fecha)) {
    aviso("Complete los campos obligatorios con valores validos.");
} elseif ($id_copiloto > 0 && $id_copiloto == $id_conductor) {
    aviso("El conductor y el copiloto no pueden ser la misma persona.");
} elseif (!existe("SELECT 1 FROM ambulancias WHERE id_ambulancia=?", "i", $id_ambulancia)
       || !existe("SELECT 1 FROM personal WHERE id_personal=? AND cargo='Conductor'", "i", $id_conductor)
       || ($id_copiloto > 0 && !existe("SELECT 1 FROM personal WHERE id_personal=?", "i", $id_copiloto))
       || ($id_ruta > 0 && !existe("SELECT 1 FROM rutas WHERE id_ruta=?", "i", $id_ruta))) {
    aviso("La ambulancia, el conductor, el copiloto o la ruta elegidos no existen.");
} else {
    // se guarda como Pendiente y se agrega la primera fila del historial
    $id_usuario = $_SESSION['usuario']['id'];
    $id_traslado = consulta("INSERT INTO traslados (id_ambulancia, id_conductor, id_copiloto, id_usuario, paciente_elemento, tipo_elemento,
                                                    punto_origen, destino, id_ruta, estado, observaciones, fecha_traslado)
                             VALUES (?,?,?,?,?,?,?,?,?,'Pendiente',?,?)",
                            "iiiissssiss", $id_ambulancia, $id_conductor, $copiloto_guardar, $id_usuario, $elemento, $tipo,
                            $origen, $destino, $ruta_guardar, $observaciones, $fecha);
    consulta("INSERT INTO seguimiento (id_traslado, id_usuario, estado, fecha_hora, comentario)
              VALUES (?,?,'Pendiente',NOW(),'Solicitud de traslado registrada')", "ii", $id_traslado, $id_usuario);
    aviso("Traslado guardado.");
}
ir('traslados.php');
