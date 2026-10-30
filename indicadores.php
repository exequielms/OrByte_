<?php
// Indicadores de satisfacción: resultados de una encuesta, con filtro por fechas y descarga en CSV
require 'includes/base.php';
entrar(DOCS);

// devuelve la fecha si viene como AAAA-MM-DD, si no devuelve vacío
function fecha_valida($fecha) {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && strtotime($fecha)) {
        return $fecha;
    }
    return '';
}

// si un texto empieza con = + - @ Excel lo toma como fórmula, así que se le pone un ' adelante
function limpiar_csv($texto) {
    if (preg_match('/^[=+\-@\t\r]/', (string)$texto)) {
        return "'" . $texto;
    }
    return $texto;
}

// encuesta elegida y fechas
$encuestas = consulta("SELECT id_encuesta, titulo FROM encuestas ORDER BY fecha_creacion DESC, id_encuesta DESC");

$id = (int)($_GET['id'] ?? 0);
if ($id == 0 && count($encuestas) > 0) {
    $id = $encuestas[0]['id_encuesta'];   // si no eligió ninguna, la más nueva
}
$encuesta = null;
if ($id) {
    $encuesta = buscar_uno("SELECT * FROM encuestas WHERE id_encuesta=?", "i", $id);
}

$desde = fecha_valida($_GET['desde'] ?? '');
$hasta = fecha_valida($_GET['hasta'] ?? '');
$fecha1 = '1970-01-01';
if ($desde != '') {
    $fecha1 = $desde;
}
$fecha2 = '2999-12-31';
if ($hasta != '') {
    $fecha2 = $hasta;
}

// cálculos
$total = 0;
$primera = '-';
$ultima = '-';
$preguntas = [];
$suma_puntos = 0;       // para el puntaje promedio general
$cantidad_respuestas = 0;
$cantidad_satisfechos = 0;

if ($encuesta) {
    $datos = buscar_uno("SELECT COUNT(*) AS n, DATE_FORMAT(MIN(fecha_hora), '%d/%m/%Y') AS primera, DATE_FORMAT(MAX(fecha_hora), '%d/%m/%Y') AS ultima
                         FROM respuestas WHERE id_encuesta=? AND fecha_hora>=? AND fecha_hora<DATE_ADD(?, INTERVAL 1 DAY)",
                        "iss", $id, $fecha1, $fecha2);
    $total = $datos['n'];
    if ($total > 0) {
        $primera = $datos['primera'];
        $ultima = $datos['ultima'];
    }

    // veces que se eligió cada opción: $veces_elegida[id_pregunta][id_opcion]
    $veces_elegida = [];
    $conteo = consulta("SELECT re.id_pregunta, re.id_opcion, COUNT(*) AS veces
                        FROM respuestas_encuesta re JOIN respuestas r ON r.id_respuesta=re.id_respuesta
                        WHERE r.id_encuesta=? AND r.fecha_hora>=? AND r.fecha_hora<DATE_ADD(?, INTERVAL 1 DAY) AND re.id_opcion IS NOT NULL
                        GROUP BY re.id_pregunta, re.id_opcion", "iss", $id, $fecha1, $fecha2);
    foreach ($conteo as $c) {
        $veces_elegida[$c['id_pregunta']][$c['id_opcion']] = (int)$c['veces'];
    }

    $lista_preguntas = consulta("SELECT id_pregunta, texto, tipo FROM preguntas WHERE id_encuesta=? ORDER BY id_pregunta", "i", $id);
    foreach ($lista_preguntas as $numero => $p) {
        $item = [
            'texto' => $p['texto'],
            'es_cerrada' => ($p['tipo'] == 'cerrada'),
            'opciones' => [],
            'respondieron' => 0,
            'es_escala' => false,
            'promedio' => '',
            'satisfechos' => '',
            'comentarios' => [],
        ];

        if ($p['tipo'] == 'cerrada') {
            $opciones = consulta("SELECT id_opcion, texto FROM opciones_respuesta WHERE id_pregunta=? ORDER BY id_opcion", "i", $p['id_pregunta']);

            // veces por opción y total de la pregunta
            $veces = [];
            $n = 0;
            $mayor = 0;
            foreach ($opciones as $k => $o) {
                $v = $veces_elegida[$p['id_pregunta']][$o['id_opcion']] ?? 0;
                $veces[$k] = $v;
                $n = $n + $v;
                if ($v > $mayor) {
                    $mayor = $v;
                }
            }

            $puntos = 0;   // en la escala: Muy mala = 1 punto ... Muy buena = 5 puntos
            foreach ($opciones as $k => $o) {
                $porcentaje = 0;
                if ($n > 0) {
                    $porcentaje = round($veces[$k] * 100 / $n, 1);
                }
                $clase = '';
                if ($mayor > 0 && $veces[$k] == $mayor) {
                    $clase = 'mayor';   // la opción más elegida se resalta
                }
                $item['opciones'][] = ['texto' => $o['texto'], 'veces' => $veces[$k], 'pct' => $porcentaje, 'clase' => $clase];
                $puntos = $puntos + $veces[$k] * ($k + 1);
            }
            $item['respondieron'] = $n;

            // el promedio solo se calcula si las opciones son justo las de la escala
            $textos = array_column($opciones, 'texto');
            if ($n > 0 && $textos === ESCALA) {
                $item['es_escala'] = true;
                $item['promedio'] = number_format($puntos / $n, 2, ',', '.');
                $buenas = $veces[3] + $veces[4];   // "Buena" y "Muy buena"
                $item['satisfechos'] = round($buenas * 100 / $n);
                $suma_puntos = $suma_puntos + $puntos;
                $cantidad_respuestas = $cantidad_respuestas + $n;
                $cantidad_satisfechos = $cantidad_satisfechos + $buenas;
            }
        } else {
            // texto libre: últimos 100 comentarios
            $item['comentarios'] = consulta("SELECT re.respuesta_texto AS texto, DATE_FORMAT(r.fecha_hora, '%d/%m/%Y') AS fecha
                                             FROM respuestas_encuesta re JOIN respuestas r ON r.id_respuesta=re.id_respuesta
                                             WHERE re.id_pregunta=? AND re.respuesta_texto IS NOT NULL AND re.respuesta_texto<>''
                                               AND r.fecha_hora>=? AND r.fecha_hora<DATE_ADD(?, INTERVAL 1 DAY)
                                             ORDER BY r.fecha_hora DESC, re.id_detalle DESC LIMIT 100",
                                            "iss", $p['id_pregunta'], $fecha1, $fecha2);
            $item['respondieron'] = count($item['comentarios']);
        }
        $preguntas[] = $item;
    }

    // descarga en CSV (se abre con Excel)
    if (isset($_GET['csv'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="indicadores-encuesta-' . $id . '.csv"');
        echo "\xEF\xBB\xBF";   // para que Excel respete los acentos
        $salida = fopen('php://output', 'w');
        fputcsv($salida, ['Pregunta', 'Respuesta', 'Cantidad', 'Porcentaje'], ';', '"', '');
        foreach ($preguntas as $p) {
            foreach ($p['opciones'] as $o) {
                fputcsv($salida, [limpiar_csv($p['texto']), limpiar_csv($o['texto']), $o['veces'], $o['pct']], ';', '"', '');
            }
            foreach ($p['comentarios'] as $c) {
                fputcsv($salida, [limpiar_csv($p['texto']), limpiar_csv($c['texto']), '', ''], ';', '"', '');
            }
        }
        exit;
    }
}

// promedio general de las escalas
$hay_satisfaccion = ($cantidad_respuestas > 0);
$promedio_general = '';
$satisfechos_general = '';
if ($hay_satisfaccion) {
    $promedio_general = number_format($suma_puntos / $cantidad_respuestas, 2, ',', '.');
    $satisfechos_general = round($cantidad_satisfechos * 100 / $cantidad_respuestas);
}

$titulo = 'Indicadores';
include 'includes/cabecera.php';
?>
<h1>Indicadores de satisfacción</h1>
<?php if (count($encuestas) == 0) { ?>
<p>Todavía no hay encuestas. Cree una en la sección Encuestas.</p>
<?php } else { ?>
<form method="get" class="filtros">
    <label>Encuesta
        <select name="id">
            <?php foreach ($encuestas as $enc) { ?>
            <option value="<?= e($enc['id_encuesta']) ?>" <?= $enc['id_encuesta'] == $id ? 'selected' : '' ?>><?= e($enc['titulo']) ?></option>
            <?php } ?>
        </select>
    </label>
    <label>Desde <input type="date" name="desde" value="<?= e($desde) ?>"></label>
    <label>Hasta <input type="date" name="hasta" value="<?= e($hasta) ?>"></label>
    <button class="boton">Ver</button>
</form>

<div class="kpis">
    <div class="kpi"><strong><?= e($total) ?></strong><span>Personas que respondieron</span></div>
    <div class="kpi"><strong><?= e($primera) ?></strong><span>Primera respuesta</span></div>
    <div class="kpi"><strong><?= e($ultima) ?></strong><span>Última respuesta</span></div>
    <?php if ($hay_satisfaccion) { ?>
    <div class="kpi"><strong><?= e($promedio_general) ?> / 5</strong><span>Puntaje promedio de satisfacción</span></div>
    <div class="kpi"><strong><?= e($satisfechos_general) ?>%</strong><span>Respondieron Buena o Muy buena</span></div>
    <?php } ?>
</div>

<?php if ($total == 0) { ?>
<p>No hay respuestas para esta encuesta en las fechas elegidas.</p>
<?php } else { ?>
<p class="no-imprimir">
    <a class="boton" href="indicadores.php?id=<?= e($id) ?>&desde=<?= e($desde) ?>&hasta=<?= e($hasta) ?>&csv=1">Descargar en Excel (CSV)</a>
    <button type="button" class="boton secundario" data-imprimir>Imprimir</button>
</p>
<?php foreach ($preguntas as $numero => $p) { ?>
<h3><?= $numero + 1 ?>. <?= e($p['texto']) ?></h3>
<?php if ($p['es_cerrada']) { ?>
<p><small><?= e($p['respondieron']) ?> personas contestaron esta pregunta</small></p>
<table class="tabla">
    <tr><th>Opción</th><th>Veces elegida</th><th>Porcentaje</th></tr>
    <?php foreach ($p['opciones'] as $o) { ?>
    <tr class="<?= e($o['clase']) ?>"><td><?= e($o['texto']) ?></td><td><?= e($o['veces']) ?></td><td><span class="barra"><span style="width: <?= e($o['pct']) ?>%"></span></span><?= e($o['pct']) ?>%</td></tr>
    <?php } ?>
</table>
<?php if ($p['es_escala']) { ?>
<p><strong>Puntaje promedio: <?= e($p['promedio']) ?> de 5</strong> - <?= e($p['satisfechos']) ?>% respondió Buena o Muy buena</p>
<?php } ?>
<?php } else { ?>
<?php if (count($p['comentarios']) == 0) { ?><p>No hay comentarios.</p><?php } ?>
<ul class="comentarios"><?php foreach ($p['comentarios'] as $c) { ?><li><?= e($c['texto']) ?> <small>(<?= e($c['fecha']) ?>)</small></li><?php } ?></ul>
<?php } ?>
<?php } ?>
<?php } ?>
<?php } ?>
<?php include 'includes/pie.php'; ?>
