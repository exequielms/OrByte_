<?php
// Traslados: lista con filtro, el mapa y el formulario de traslado nuevo
require 'includes/base.php';
entrar(TRANSP);

// filtro por estado (traslados.php?estado=En curso)
$filtro = '';
if (in_array($_GET['estado'] ?? '', ESTADOS)) {
    $filtro = $_GET['estado'];
}

$lista = consulta("SELECT t.*, DATE_FORMAT(t.fecha_traslado, '%d/%m/%Y') AS fecha, a.patente, CONCAT(p.nombre,' ',p.apellido) AS conductor
                   FROM traslados t
                   JOIN ambulancias a ON a.id_ambulancia=t.id_ambulancia
                   JOIN personal p ON p.id_personal=t.id_conductor
                   WHERE (?='' OR t.estado=?)
                   ORDER BY t.fecha_traslado DESC, t.id_traslado DESC", "ss", $filtro, $filtro);

// para los select del formulario
$ambulancias = consulta("SELECT id_ambulancia, CONCAT(patente,' - ',modelo) AS nombre FROM ambulancias WHERE estado<>'Mantenimiento' ORDER BY patente");
$conductores = consulta("SELECT id_personal, CONCAT(nombre,' ',apellido) AS nombre FROM personal WHERE cargo='Conductor' ORDER BY apellido");
$copilotos = consulta("SELECT id_personal, CONCAT(nombre,' ',apellido) AS nombre FROM personal WHERE cargo IN ('Enfermera','Auxiliar') ORDER BY apellido");
$rutas = consulta("SELECT id_ruta, nombre FROM rutas ORDER BY nombre");

// mapa: cada ambulancia con su traslado activo más reciente, si tiene
$mapa_amb = consulta("SELECT a.id_ambulancia, CONCAT(a.patente,' - ',a.modelo) nombre, a.estado estado_amb,
                             IFNULL(t.punto_origen,'') origen_mapa, IFNULL(t.destino,'') destino_mapa, IFNULL(t.estado,'') estado_traslado, IFNULL(t.hora_salida,'') hora_salida
                      FROM ambulancias a
                      LEFT JOIN traslados t ON t.id_traslado = (SELECT t2.id_traslado FROM traslados t2 WHERE t2.id_ambulancia=a.id_ambulancia
                                                                AND t2.estado IN ('Pendiente','En curso','Llegado') ORDER BY t2.fecha_traslado DESC, t2.id_traslado DESC LIMIT 1)
                      ORDER BY a.patente");
// la hora de salida pasa a timestamp para estimar la posición
foreach ($mapa_amb as $i => $m) {
    $mapa_amb[$i]['salida_mapa'] = '';
    if ($m['hora_salida'] != '') {
        $mapa_amb[$i]['salida_mapa'] = strtotime($m['hora_salida']);
    }
}

$titulo = 'Traslados';
include 'includes/cabecera.php';
?>
<h1>Gestión de traslados</h1>
<form method="get">
    <label>Filtrar por estado
        <select name="estado"><option value="">Todos</option><?php opciones(ESTADOS, $filtro); ?></select>
    </label>
    <button class="boton">Filtrar</button>
</form>

<table class="tabla">
    <tr><th>Fecha</th><th>Ambulancia</th><th>Conductor</th><th>Se traslada</th><th>Origen y destino</th><th>Estado</th><th></th></tr>
    <?php foreach ($lista as $t) { ?>
    <tr>
        <td><?= e($t['fecha']) ?></td><td><?= e($t['patente']) ?></td><td><?= e($t['conductor']) ?></td>
        <td><?= e($t['tipo_elemento']) ?>: <?= e($t['paciente_elemento']) ?></td>
        <td><?= e($t['punto_origen']) ?> a <?= e($t['destino']) ?></td>
        <td><span class="estado estado-<?= e(clase_estado($t['estado'])) ?>"><?= e($t['estado']) ?></span></td>
        <td><a href="traslado.php?id=<?= e($t['id_traslado']) ?>">Seguimiento</a></td>
    </tr>
    <?php } ?>
    <?php if (count($lista) == 0) { ?><tr><td colspan="7">No hay traslados.</td></tr><?php } ?>
</table>

<h2>Mapa de ambulancias</h2>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<form id="form-mapa">
    <label>Ver ambulancia
        <select id="mapa-ambulancia"><option value="">Seleccione una ambulancia</option><option value="todas">Todas las ambulancias</option>
            <?php foreach ($mapa_amb as $m) { ?>
            <option value="<?= e($m['id_ambulancia']) ?>" data-nombre="<?= e($m['nombre']) ?>" data-estado="<?= e($m['estado_amb']) ?>" data-origen="<?= e($m['origen_mapa']) ?>" data-destino="<?= e($m['destino_mapa']) ?>" data-traslado="<?= e($m['estado_traslado']) ?>" data-salida="<?= e($m['salida_mapa']) ?>"><?= e($m['nombre']) ?> (<?= e($m['estado_amb']) ?>)</option>
            <?php } ?>
        </select>
    </label>
    <label>Destino de la simulación (escriba un lugar o haga clic en el mapa)
        <input type="text" id="sim-destino" maxlength="300" placeholder="Ej: Paysandú">
    </label>
    <label>Duración de la simulación
        <select id="sim-duracion"><option value="20">20 segundos</option><option value="40" selected>40 segundos</option><option value="90">90 segundos</option></select>
    </label>
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px;">
        <button type="button" class="boton" id="sim-iniciar">Simular recorrido</button>
        <button type="button" class="boton" id="sim-detener">Detener</button>
    </div>
    <p id="mapa-info">Seleccione una ambulancia para ver su traslado en el mapa.</p>
    <div id="sim-panel" hidden>
        <table class="tabla">
            <tr><th>Velocidad promedio</th><th>Recorrido</th><th>Tiempo restante</th><th>Llegada estimada</th></tr>
            <tr><td id="sim-vel">-</td><td id="sim-rec">-</td><td id="sim-resta">-</td><td id="sim-llega">-</td></tr>
        </table>
    </div>
    <div id="visualizar" style="height: 460px; border: 1px solid var(--borde); border-radius: 8px; isolation: isolate;"></div>
</form>

<h2>Nuevo traslado</h2>
<form method="post" action="guardar_traslado.php">
    <?php csrf(); ?>
    <label>Ambulancia
        <select name="id_ambulancia"><option value="">Seleccione</option>
            <?php foreach ($ambulancias as $a) { ?><option value="<?= e($a['id_ambulancia']) ?>"><?= e($a['nombre']) ?></option><?php } ?>
        </select>
    </label>
    <label>Conductor
        <select name="id_conductor"><option value="">Seleccione</option>
            <?php foreach ($conductores as $c) { ?><option value="<?= e($c['id_personal']) ?>"><?= e($c['nombre']) ?></option><?php } ?>
        </select>
    </label>
    <label>Copiloto (opcional)
        <select name="id_copiloto"><option value="">Sin copiloto</option>
            <?php foreach ($copilotos as $c) { ?><option value="<?= e($c['id_personal']) ?>"><?= e($c['nombre']) ?></option><?php } ?>
        </select>
    </label>
    <label>Tipo de elemento
        <select name="tipo_elemento"><?php opciones(TIPOS); ?></select>
    </label>
    <label>Paciente o elemento <input type="text" name="paciente_elemento" maxlength="300"></label>
    <label>Ruta (opcional)
        <select name="id_ruta"><option value="">Sin ruta predefinida</option>
            <?php foreach ($rutas as $r) { ?><option value="<?= e($r['id_ruta']) ?>"><?= e($r['nombre']) ?></option><?php } ?>
        </select>
    </label>
    <label>Punto de origen <input type="text" name="punto_origen" maxlength="300" value="Hospital de Clínicas"></label>
    <label>Destino <input type="text" name="destino" maxlength="300"></label>
    <label>Fecha del traslado <input type="date" name="fecha_traslado"></label>
    <label>Observaciones <textarea name="observaciones" maxlength="2000"></textarea></label>
    <button class="boton">Guardar traslado</button>
</form>
<?php include 'includes/pie.php'; ?>
