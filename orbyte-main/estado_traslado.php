<?php
// Cambio de estado de un traslado. Guarda también el historial (solo POST)
require 'includes/base.php';
entrar(TRANSP);

$id = (int)($_POST['id'] ?? 0);
$t = buscar_uno("SELECT t.id_ambulancia, t.estado, a.estado AS estado_amb, r.tiempo_estimado_min
                 FROM traslados t
                 JOIN ambulancias a ON a.id_ambulancia=t.id_ambulancia
                 LEFT JOIN rutas r ON r.id_ruta=t.id_ruta
                 WHERE t.id_traslado=?", "i", $id);
if (!$t) {
    aviso("No se encontró el traslado.");
    ir('traslados.php');
}

// el estado nuevo tiene que estar permitido desde el estado que tiene guardado
$nuevo = $_POST['estado'] ?? '';
if (!in_array($nuevo, TRANSICIONES[$t['estado']])) {
    aviso("Cambio de estado no válido.");
    ir("traslado.php?id=$id");
}

$ahora = date('Y-m-d H:i:s');
consulta("UPDATE traslados SET estado=? WHERE id_traslado=?", "si", $nuevo, $id);

// según el estado nuevo se anota una hora u otra
if ($nuevo == 'En curso') {
    consulta("UPDATE traslados SET hora_salida=? WHERE id_traslado=?", "si", $ahora, $id);
    // con ruta se puede calcular la llegada estimada
    if ($t['tiempo_estimado_min']) {
        consulta("UPDATE traslados SET hora_estimada_llegada=DATE_ADD(?, INTERVAL ? MINUTE) WHERE id_traslado=?",
                 "sii", $ahora, $t['tiempo_estimado_min'], $id);
    }
} elseif ($nuevo == 'Llegado') {
    consulta("UPDATE traslados SET hora_efectiva_llegada=? WHERE id_traslado=?", "si", $ahora, $id);
} elseif ($nuevo == 'Finalizado') {
    consulta("UPDATE traslados SET hora_regreso=? WHERE id_traslado=?", "si", $ahora, $id);
}

// la ambulancia también cambia de estado (salvo que esté en mantenimiento)
if ($t['estado_amb'] != 'Mantenimiento') {
    if ($nuevo == 'En curso') {
        consulta("UPDATE ambulancias SET estado='En servicio' WHERE id_ambulancia=?", "i", $t['id_ambulancia']);
    } elseif ($nuevo == 'Finalizado' || $nuevo == 'Cancelado') {
        consulta("UPDATE ambulancias SET estado='Disponible' WHERE id_ambulancia=?", "i", $t['id_ambulancia']);
    }
}

// historial
consulta("INSERT INTO seguimiento (id_traslado, id_usuario, estado, fecha_hora, comentario) VALUES (?,?,?,?,?)",
         "iisss", $id, $_SESSION['usuario']['id'], $nuevo, $ahora, trim($_POST['comentario'] ?? ''));
aviso("Estado actualizado.");
ir("traslado.php?id=$id");
