<?php
require 'includes/base.php';
entrar(array_merge(DOCS, TRANSP));

// lo que se muestra en el panel y la consulta de cada uno
$consultas = [
    'Documentos publicados' => "SELECT COUNT(*) AS n FROM documentos",
    'Encuestas activas'     => "SELECT COUNT(*) AS n FROM encuestas WHERE estado='Activa'",
    'Respuestas recibidas'  => "SELECT COUNT(*) AS n FROM respuestas",
    'Traslados pendientes'  => "SELECT COUNT(*) AS n FROM traslados WHERE estado='Pendiente'",
    'Traslados en curso'    => "SELECT COUNT(*) AS n FROM traslados WHERE estado='En curso'",
];
$resumen = [];
foreach ($consultas as $concepto => $sql) {
    $fila = buscar_uno($sql);
    $resumen[$concepto] = $fila['n'];
}

$titulo = 'Panel';
include 'includes/cabecera.php';
?>
<h1>Bienvenido, <?= e($_SESSION['usuario']['nombre']) ?></h1>
<p>Rol: <?= e($_SESSION['usuario']['rol']) ?></p>
<table class="tabla">
    <tr><th>Concepto</th><th>Cantidad</th></tr>
    <?php foreach ($resumen as $concepto => $cantidad) { ?>
    <tr><td><?= e($concepto) ?></td><td><?= e($cantidad) ?></td></tr>
    <?php } ?>
</table>
<?php include 'includes/pie.php'; ?>
