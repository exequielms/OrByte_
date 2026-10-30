<?php
// Revisa si el servidor quedó bien configurado (PHP, base y archivos).
// Solo abre desde la misma compu. Borrar este archivo cuando termine la instalación.
header('Content-Type: text/html; charset=utf-8');
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Esta página solo se puede abrir desde el mismo servidor.');
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

// cada chequeo: [texto, ok (true/false), qué hacer si falló]
$resultados = [];
function chequeo($texto, $ok, $ayuda) {
    global $resultados;
    $resultados[] = [$texto, (bool)$ok, $ayuda];
}

// php
chequeo('PHP 8 o más nuevo (este servidor tiene ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.0.0', '>='), 'Actualice XAMPP a una versión con PHP 8.');
chequeo('Extensión mbstring activa', extension_loaded('mbstring'), 'En php.ini active la línea extensión=mbstring y reinicie Apache.');
chequeo('Extensión mysqli activa', extension_loaded('mysqli'), 'En php.ini active la línea extensión=mysqli y reinicie Apache.');

// que estén los archivos
$archivos = ['includes/base.php', 'includes/cabecera.php', 'includes/pie.php', 'validar_login.php', 'perfil.php', 'guardar_perfil.php',
             'usuarios.php', 'guardar_usuario.php', 'traslados.php', 'assets/estilos.css', 'assets/app.js'];
foreach ($archivos as $archivo) {
    chequeo("Existe el archivo $archivo", is_file(__DIR__ . '/' . $archivo), "Copie $archivo a esa carpeta del proyecto.");
}

// base de datos
mysqli_report(MYSQLI_REPORT_OFF);
$db = @new mysqli('localhost', 'root', '', 'orbyte_hospital');
$conectado = !$db->connect_errno;
chequeo('Conexión con la base orbyte_hospital', $conectado, 'No se pudo conectar: ' . $db->connect_error);

if ($conectado) {
    $db->set_charset('utf8mb4');

    // columnas que tiene que tener la tabla usuarios
    $columnas = [];
    $resultado = $db->query('SHOW COLUMNS FROM usuarios');
    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $columnas[] = $fila['Field'];
        }
    }
    $necesarias = ['correo', 'numero_funcionario', 'departamento', 'estado', 'foto', 'ultimo_login', 'ultima_ip', 'contrasena_cambiada',
                   'cambiar_contrasena', 'intentos_fallidos', 'bloqueado_hasta', 'version_sesion', 'creado_por', 'fecha_creacion'];
    $faltan = array_diff($necesarias, $columnas);
    chequeo('La tabla usuarios tiene todas las columnas', count($faltan) == 0, 'Importe database/migracion_usuarios.sql en phpMyAdmin. Faltan: ' . implode(', ', $faltan));

    $tabla = $db->query("SHOW TABLES LIKE 'auditoria'");
    chequeo('Existe la tabla auditoria', $tabla && $tabla->num_rows > 0, 'Importe database/migracion_usuarios.sql en phpMyAdmin.');
}

// carpeta de fotos
$fotos = __DIR__ . '/fotos';
if (is_dir($fotos)) {
    $se_puede = is_writable($fotos);
} else {
    $se_puede = is_writable(__DIR__);
}
chequeo('La carpeta de fotos se puede usar', $se_puede, 'Déle permiso de escritura a la carpeta del proyecto o cree la carpeta fotos.');

// cuántos fallaron
$fallas = 0;
foreach ($resultados as $r) {
    if (!$r[1]) {
        $fallas++;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Diagnóstico de OrByte</title>
<style>
body { font-family: "Segoe UI", Arial, sans-serif; max-width: 760px; margin: 30px auto; padding: 0 16px; color: #12263a; }
h1 { color: #0b3a63; }
.resumen { padding: 12px 16px; border-radius: 8px; font-weight: 700; margin-bottom: 18px; }
.bien { background: #cdecd6; color: #135c2e; }
.mal { background: #fbe0e0; color: #8a1f1f; }
ul { list-style: none; padding: 0; }
li { padding: 10px 12px; border: 1px solid #d5e4f0; border-radius: 8px; margin-bottom: 8px; }
li.ok::before { content: "OK: "; color: #135c2e; font-weight: 700; }
li.falla { border-color: #c0392b; background: #fff6f6; }
li.falla::before { content: "Falla: "; color: #c0392b; font-weight: 700; }
small { display: block; margin-top: 4px; color: #8a1f1f; }
</style>
</head>
<body>
<h1>Diagnóstico de OrByte</h1>
<?php if ($fallas > 0) { ?>
<div class="resumen mal">Se encontraron <?= $fallas ?> problema(s). Arréglelos de arriba hacia abajo.</div>
<?php } else { ?>
<div class="resumen bien">Todo está en orden. Si algo sigue fallando, revise el archivo apache\logs\error.log de XAMPP.</div>
<?php } ?>
<ul>
<?php foreach ($resultados as $r) { ?>
    <li class="<?= $r[1] ? 'ok' : 'falla' ?>"><?= htmlspecialchars($r[0]) ?><?php if (!$r[1]) { ?><small><?= htmlspecialchars($r[2]) ?></small><?php } ?></li>
<?php } ?>
</ul>
<p><small style="color:#5b7186">Cuando termine, borre este archivo (diagnostico.php) del servidor.</small></p>
</body>
</html>
