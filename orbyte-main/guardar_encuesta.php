<?php
// Acciones de las encuestas, todas por POST. Cuál es la acción viene en $_POST['accion'].
require 'includes/base.php';
entrar(DOCS);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('encuestas.php');
}

$accion = $_POST['accion'] ?? '';
$id_encuesta = (int)($_POST['id_encuesta'] ?? 0);
$id_usuario = $_SESSION['usuario']['id'];

// nueva encuesta
if ($accion == 'nueva') {
    $titulo_enc = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($titulo_enc == '' || mb_strlen($titulo_enc) > 200 || mb_strlen($descripcion) > 2000) {
        aviso("Escriba un título de hasta 200 letras.");
        ir('encuestas.php');
    }
    $nuevo = consulta("INSERT INTO encuestas (titulo, descripcion, fecha_creacion, estado, id_usuario) VALUES (?,?,NOW(),'Activa',?)",
                      "ssi", $titulo_enc, $descripcion, $id_usuario);
    aviso("Encuesta creada. Ahora agregue sus preguntas.");
    ir("encuestas.php?id=$nuevo#preguntas");
}

// de acá para abajo hace falta que la encuesta exista
if ($id_encuesta == 0 || !existe("SELECT 1 FROM encuestas WHERE id_encuesta=?", "i", $id_encuesta)) {
    aviso("La encuesta no existe.");
    ir('encuestas.php');
}

// editar título y descripción
if ($accion == 'editar') {
    $titulo_enc = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    if ($titulo_enc == '' || mb_strlen($titulo_enc) > 200 || mb_strlen($descripcion) > 2000) {
        aviso("Escriba un título de hasta 200 letras.");
    } else {
        consulta("UPDATE encuestas SET titulo=?, descripcion=? WHERE id_encuesta=?", "ssi", $titulo_enc, $descripcion, $id_encuesta);
        aviso("Datos de la encuesta guardados.");
    }
    ir("encuestas.php?id=$id_encuesta#preguntas");
}

// activar o desactivar
if ($accion == 'estado') {
    consulta("UPDATE encuestas SET estado = IF(estado='Activa','Inactiva','Activa') WHERE id_encuesta=?", "i", $id_encuesta);
    aviso("Estado de la encuesta actualizado.");
    ir('encuestas.php');
}

// duplicar (la copia queda inactiva)
if ($accion == 'clonar') {
    $original = buscar_uno("SELECT titulo, descripcion FROM encuestas WHERE id_encuesta=?", "i", $id_encuesta);
    $nuevo = consulta("INSERT INTO encuestas (titulo, descripcion, fecha_creacion, estado, id_usuario) VALUES (?,?,NOW(),'Inactiva',?)",
                      "ssi", mb_substr($original['titulo'], 0, 190) . ' (copia)', $original['descripcion'], $id_usuario);
    $preguntas = consulta("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta=? ORDER BY id_pregunta", "i", $id_encuesta);
    foreach ($preguntas as $p) {
        $nueva_pregunta = consulta("INSERT INTO preguntas (texto, tipo, id_encuesta) VALUES (?,?,?)", "ssi", $p['texto'], $p['tipo'], $nuevo);
        $opciones = consulta("SELECT texto FROM opciones_respuesta WHERE id_pregunta=? ORDER BY id_opcion", "i", $p['id_pregunta']);
        foreach ($opciones as $o) {
            consulta("INSERT INTO opciones_respuesta (texto, id_pregunta) VALUES (?,?)", "si", $o['texto'], $nueva_pregunta);
        }
    }
    aviso("Encuesta duplicada. La copia queda inactiva hasta que la active.");
    ir("encuestas.php?id=$nuevo#preguntas");
}

// borrar (las preguntas y respuestas se borran solas por el CASCADE)
if ($accion == 'borrar') {
    consulta("DELETE FROM encuestas WHERE id_encuesta=?", "i", $id_encuesta);
    aviso("Encuesta eliminada.");
    ir('encuestas.php');
}

// agregar pregunta
if ($accion == 'pregunta') {
    $texto = trim($_POST['texto'] ?? '');
    $tipo = $_POST['tipo'] ?? '';

    // opciones que escribió el usuario (sin repetidas ni vacías)
    $opciones = [];
    $escritas = (array)($_POST['opciones'] ?? []);
    foreach ($escritas as $opcion) {
        if (!is_string($opcion)) {
            continue;
        }
        $opcion = trim($opcion);
        if ($opcion === '') {
            continue;
        }
        $ya_esta = false;
        foreach ($opciones as $otra) {
            if (mb_strtolower($otra) == mb_strtolower($opcion)) {
                $ya_esta = true;
            }
        }
        if (!$ya_esta) {
            $opciones[] = $opcion;
        }
    }
    // los tipos "escala" y "si_no" ya traen sus opciones
    if ($tipo == 'escala') {
        $opciones = ESCALA;
    }
    if ($tipo == 'si_no') {
        $opciones = ['Sí', 'No'];
    }
    $es_cerrada = ($tipo != 'abierta');

    $hay_larga = false;
    foreach ($opciones as $o) {
        if (mb_strlen($o) > 200) {
            $hay_larga = true;
        }
    }

    if ($texto == '' || mb_strlen($texto) > 1000 || !in_array($tipo, ['escala', 'si_no', 'unica', 'abierta'])) {
        aviso("Escriba la pregunta y elija el tipo.");
    } elseif ($es_cerrada && count($opciones) < 2) {
        aviso("Una pregunta para elegir una opción necesita al menos dos opciones diferentes.");
    } elseif (count($opciones) > 15 || $hay_larga) {
        aviso("Use hasta 15 opciones de no más de 200 letras cada una.");
    } else {
        $tipo_guardado = 'abierta';
        if ($es_cerrada) {
            $tipo_guardado = 'cerrada';
        }
        $id_pregunta = consulta("INSERT INTO preguntas (texto, tipo, id_encuesta) VALUES (?,?,?)", "ssi", $texto, $tipo_guardado, $id_encuesta);
        if ($es_cerrada) {
            foreach ($opciones as $o) {
                consulta("INSERT INTO opciones_respuesta (texto, id_pregunta) VALUES (?,?)", "si", $o, $id_pregunta);
            }
        }
        aviso("Pregunta agregada.");
    }
    ir("encuestas.php?id=$id_encuesta#preguntas");
}

// borrar pregunta
if ($accion == 'borrar_pregunta') {
    $id_pregunta = (int)($_POST['id_pregunta'] ?? 0);
    consulta("DELETE FROM preguntas WHERE id_pregunta=? AND id_encuesta=?", "ii", $id_pregunta, $id_encuesta);
    aviso("Pregunta eliminada junto con sus respuestas.");
    ir("encuestas.php?id=$id_encuesta#preguntas");
}

ir('encuestas.php');
