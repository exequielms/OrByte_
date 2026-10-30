<?php
// Guarda lo que respondió el paciente (público y anónimo, solo recibe POST)
require 'includes/base.php';
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('encuesta.php');
}

$id_encuesta = (int)($_POST['id_encuesta'] ?? 0);
if (!existe("SELECT 1 FROM encuestas WHERE id_encuesta=? AND estado='Activa'", "i", $id_encuesta)) {
    ir('encuesta.php');
}

// opciones válidas de cada pregunta de esta encuesta
$opciones_validas = [];
$opciones = consulta("SELECT id_pregunta, id_opcion FROM opciones_respuesta
                      WHERE id_pregunta IN (SELECT id_pregunta FROM preguntas WHERE id_encuesta=?)", "i", $id_encuesta);
foreach ($opciones as $o) {
    $opciones_validas[$o['id_pregunta']][$o['id_opcion']] = true;
}

// respuestas que el paciente contestó de verdad
$respuestas = [];
$preguntas = consulta("SELECT id_pregunta, tipo FROM preguntas WHERE id_encuesta=?", "i", $id_encuesta);
foreach ($preguntas as $p) {
    $id_pregunta = $p['id_pregunta'];
    if ($p['tipo'] == 'cerrada') {
        $id_opcion = (int)($_POST['opcion'][$id_pregunta] ?? 0);
        if (isset($opciones_validas[$id_pregunta][$id_opcion])) {
            $respuestas[] = ['pregunta' => $id_pregunta, 'opcion' => $id_opcion, 'texto' => null];
        }
    } else {
        $texto = mb_substr(trim($_POST['texto'][$id_pregunta] ?? ''), 0, 2000);
        if ($texto !== '') {
            $respuestas[] = ['pregunta' => $id_pregunta, 'opcion' => null, 'texto' => $texto];
        }
    }
}

// si no contestó nada no se guarda un envío vacío
if (count($respuestas) > 0) {
    $id_respuesta = consulta("INSERT INTO respuestas (id_encuesta, fecha_hora) VALUES (?, NOW())", "i", $id_encuesta);
    foreach ($respuestas as $r) {
        consulta("INSERT INTO respuestas_encuesta (id_respuesta, id_pregunta, id_opcion, respuesta_texto) VALUES (?,?,?,?)",
                 "iiis", $id_respuesta, $r['pregunta'], $r['opcion'], $r['texto']);
    }
}
ir('encuesta.php?gracias=1');
