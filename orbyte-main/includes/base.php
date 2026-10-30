<?php
// Archivo común: todas las páginas lo incluyen con require 'includes/base.php';
// Tiene la conexión a la base, la sesión, las listas fijas y unas funciones de ayuda.

ini_set('display_errors', 0);
session_start();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// conexión a la base (XAMPP: usuario root sin contraseña)
$db = new mysqli('localhost', 'root', '', 'orbyte_hospital');
$db->set_charset('utf8mb4');

// donde se guardan los PDF y las fotos
define('CARPETA_PDF', __DIR__ . '/../documentos');
define('CARPETA_FOTOS', __DIR__ . '/../fotos');

// roles: quién puede entrar a cada módulo
define('DOCS', ['Administrador', 'Supervisora', 'Archivista', 'Recepcionista']);   // documentos y encuestas
define('TRANSP', ['Administrador', 'Supervisora', 'Coordinador Transportes']);     // ambulancias y traslados
define('ROLES_TODOS', ['Administrador', 'Supervisora', 'Archivista', 'Recepcionista', 'Coordinador Transportes']);

// qué roles puede crear cada uno
define('PUEDEN_CREAR', [
    'Administrador' => ROLES_TODOS,
    'Supervisora' => ['Archivista', 'Recepcionista', 'Coordinador Transportes'],
]);

define('PERMISOS', [
    'Administrador' => ['Lectura', 'Edición', 'Administración total'],
    'Supervisora' => ['Lectura', 'Edición', 'Administración de usuarios con rol inferior'],
    'Archivista' => ['Lectura', 'Edición'],
    'Recepcionista' => ['Lectura', 'Edición'],
    'Coordinador Transportes' => ['Lectura', 'Edición'],
]);

// listas para los select de los formularios
define('DEPARTAMENTOS', ['Direccion', 'Administracion', 'Archivo y Documentacion', 'Recepcion', 'Transportes', 'Enfermeria',
                         'Informatica (DTI)', 'Cardiologia', 'Cirugia', 'Imagenologia', 'Nefrologia', 'Farmacia']);
define('DOMINIO_CORREO', '');   // si se pone un dominio (ej: hc.edu.uy) los correos nuevos tienen que terminar así
define('ESTADOS_AMB', ['Disponible', 'En servicio', 'Mantenimiento']);
define('CARGOS', ['Conductor', 'Enfermera', 'Auxiliar']);
define('CATEGORIAS', ['Salud Reproductiva', 'Cirugia', 'Imagenologia', 'Cardiologia', 'Farmacologia', 'Nefrologia', 'Enfermeria']);
define('TIPOS', ['Paciente', 'Material biologico', 'Equipamiento', 'Insumo']);
define('ESCALA', ['Muy mala', 'Mala', 'Regular', 'Buena', 'Muy buena']);
define('ESTADOS', ['Pendiente', 'En curso', 'Llegado', 'Finalizado', 'Cancelado']);

// de qué estado a cuál se puede pasar en un traslado
define('TRANSICIONES', [
    'Pendiente' => ['En curso', 'Cancelado'],
    'En curso' => ['Llegado', 'Cancelado'],
    'Llegado' => ['Finalizado'],
    'Finalizado' => [],
    'Cancelado' => [],
]);


// ---- base de datos ----

// Consulta preparada. En $tipos va el tipo de cada ? (i = entero, d = decimal, s = texto).
// Ej: consulta("SELECT * FROM rutas WHERE id_ruta=?", "i", 5);
// Con un SELECT devuelve las filas, con un INSERT devuelve el id nuevo.
function consulta($sql, $tipos = "", ...$valores) {
    global $db;
    $stmt = $db->prepare($sql);
    if ($tipos != "") {
        $stmt->bind_param($tipos, ...$valores);
    }
    $stmt->execute();
    $resultado = $stmt->get_result();
    if ($resultado) {
        return $resultado->fetch_all(MYSQLI_ASSOC);
    }
    return $stmt->insert_id;
}

// como consulta() pero trae solo la primera fila (null si no hay)
function buscar_uno($sql, $tipos = "", ...$valores) {
    $filas = consulta($sql, $tipos, ...$valores);
    if (count($filas) > 0) {
        return $filas[0];
    }
    return null;
}

// true si la consulta encuentra algo
function existe($sql, $tipos = "", ...$valores) {
    $filas = consulta($sql, $tipos, ...$valores);
    return count($filas) > 0;
}


// ---- ayudas ----

// escapa el texto antes de mostrarlo (por el XSS)
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// redirige y corta el script
function ir($url) {
    header("Location: $url");
    exit;
}

// mensaje que se muestra una sola vez, en la próxima página
function aviso($mensaje) {
    $_SESSION['aviso'] = $mensaje;
}

// igual pero con una lista de errores
function errores($lista) {
    $_SESSION['errores'] = array_values((array)$lista);
}

function ip_cliente() {
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

// arma los <option> de un <select> y marca el que está elegido
function opciones($valores, $actual = '') {
    foreach ($valores as $valor) {
        $marca = '';
        if ($valor == $actual) {
            $marca = ' selected';
        }
        echo '<option' . $marca . '>' . e($valor) . '</option>';
    }
}

// "En curso" pasa a "En-curso" para usarlo como clase del CSS
function clase_estado($estado) {
    return str_replace(' ', '-', $estado);
}


// ---- sesión: en cada página se vuelve a chequear al usuario ----
if (isset($_SESSION['usuario'])) {
    $fila = buscar_uno("SELECT id_usuario, nombre, apellido, rol, estado, foto, cambiar_contrasena, version_sesion
                        FROM usuarios WHERE id_usuario=?", "i", $_SESSION['usuario']['id']);

    // si borraron la cuenta, la desactivaron o cerraron las sesiones, esta ya no sirve
    $version_sesion = $_SESSION['version'] ?? 0;
    if (!$fila || $fila['estado'] != 'Activa' || (int)$fila['version_sesion'] !== (int)$version_sesion) {
        unset($_SESSION['usuario'], $_SESSION['version'], $_SESSION['anterior'], $_SESSION['cambiar']);
        $_SESSION['aviso'] = 'Su sesión ya no es válida. Ingrese nuevamente.';
    } else {
        $iniciales = mb_strtoupper(mb_substr($fila['nombre'], 0, 1) . mb_substr($fila['apellido'], 0, 1));
        $_SESSION['usuario'] = [
            'id' => $fila['id_usuario'],
            'nombre' => $fila['nombre'] . ' ' . $fila['apellido'],
            'rol' => $fila['rol'],
            'foto' => $fila['foto'],
            'iniciales' => $iniciales,
        ];
        $_SESSION['cambiar'] = (bool)$fila['cambiar_contrasena'];
    }
}


// ---- token CSRF ----
// cada formulario manda el token escondido; si no coincide con el de la sesión se rechaza
if (empty($_SESSION['token'])) {
    $_SESSION['token'] = bin2hex(random_bytes(16));
}

// campo oculto con el token, va dentro de cada <form method="post">
function csrf() {
    echo '<input type="hidden" name="token" value="' . $_SESSION['token'] . '">';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token_enviado = $_POST['token'] ?? '';
    if (!hash_equals($_SESSION['token'], $token_enviado)) {
        die('Token inválido');
    }
}


// ---- permisos ----

// Deja pasar solo a quien inició sesión y tiene alguno de los roles indicados.
// Con $libre = true deja pasar también al que todavía tiene que cambiar la contraseña inicial.
function entrar($roles, $libre = false) {
    if (!isset($_SESSION['usuario'])) {
        ir('login.php');
    }
    if (!$libre && !empty($_SESSION['cambiar'])) {
        aviso('Debe cambiar su contraseña inicial antes de continuar.');
        ir('perfil.php?clave=1');
    }
    if (!in_array($_SESSION['usuario']['rol'], $roles)) {
        aviso('No tiene permisos para esa sección.');
        ir('panel.php');
    }
}

// opciones del menú según el rol: nombre => página
function menu() {
    $rol = $_SESSION['usuario']['rol'];
    $menu = ['Panel' => 'panel.php'];
    if (in_array($rol, DOCS)) {
        $menu['Documentos'] = 'documentos.php';
        $menu['Encuestas'] = 'encuestas.php';
        $menu['Indicadores'] = 'indicadores.php';
    }
    if (in_array($rol, TRANSP)) {
        $menu['Ambulancias'] = 'ambulancias.php';
        $menu['Personal'] = 'personal.php';
        $menu['Rutas'] = 'rutas.php';
        $menu['Traslados'] = 'traslados.php';
    }
    if (isset(PUEDEN_CREAR[$rol])) {
        $menu['Usuarios'] = 'usuarios.php';
    }
    return $menu;
}

// true si el usuario puede gestionar cuentas con ese rol
function puede_gestionar($rol) {
    $mi_rol = $_SESSION['usuario']['rol'] ?? '';
    if (!isset(PUEDEN_CREAR[$mi_rol])) {
        return false;
    }
    return in_array($rol, PUEDEN_CREAR[$mi_rol], true);
}

// contraseña temporal de 12 caracteres (siempre con alguna letra y algún número)
function clave_temporal() {
    $caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $clave = '';
    $tiene_letra = false;
    $tiene_numero = false;
    while (!$tiene_letra || !$tiene_numero) {
        $clave = '';
        for ($i = 0; $i < 12; $i++) {
            $clave .= $caracteres[random_int(0, strlen($caracteres) - 1)];
        }
        $tiene_letra = preg_match('/[A-Za-z]/', $clave);
        $tiene_numero = preg_match('/[0-9]/', $clave);
    }
    return $clave;
}

// guarda en auditoria quién hizo qué (se ve en Usuarios > Actividad reciente)
function auditar($accion, $objetivo = null, $detalle = '') {
    consulta("INSERT INTO auditoria (fecha_hora, id_actor, accion, id_objetivo, detalle, ip) VALUES (NOW(),?,?,?,?,?)",
             "isiss", $_SESSION['usuario']['id'] ?? null, $accion, $objetivo, mb_substr($detalle, 0, 255), ip_cliente());
}
