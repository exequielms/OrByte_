<?php
// Valida usuario y contraseña del login
require 'includes/base.php';

$usuario = strtolower(trim($_POST['usuario'] ?? ''));
$clave = (string)($_POST['contrasena'] ?? '');

$u = buscar_uno("SELECT *, (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueada
                 FROM usuarios WHERE usuario=?", "s", $usuario);

// 1) cuenta bloqueada por intentos fallidos
if ($u && $u['bloqueada']) {
    aviso('Demasiados intentos fallidos. Espere unos minutos e intente de nuevo.');
    ir('login.php');
}

// 2) usuario y contraseña correctos
if ($u && password_verify($clave, $u['contrasena'])) {
    if ($u['estado'] != 'Activa') {
        aviso('Su cuenta está inactiva. Comuníquese con un administrador.');
        ir('login.php');
    }

    session_regenerate_id(true);

    // se guarda cuándo y desde dónde entró la última vez (después se ve en el perfil)
    $fecha_anterior = 'Primer ingreso';
    if ($u['ultimo_login']) {
        $fecha_anterior = date('d/m/Y H:i', strtotime($u['ultimo_login']));
    }
    $ip_anterior = $u['ultima_ip'] ?: '-';
    $_SESSION['anterior'] = ['fecha' => $fecha_anterior, 'ip' => $ip_anterior];

    $_SESSION['version'] = (int)$u['version_sesion'];
    $_SESSION['usuario'] = [
        'id' => $u['id_usuario'],
        'nombre' => $u['nombre'] . ' ' . $u['apellido'],
        'rol' => $u['rol'],
        'foto' => $u['foto'],
        'iniciales' => '',
    ];

    consulta("UPDATE usuarios SET ultimo_login=NOW(), ultima_ip=?, intentos_fallidos=0, bloqueado_hasta=NULL WHERE id_usuario=?",
             "si", ip_cliente(), $u['id_usuario']);

    if ($u['cambiar_contrasena']) {
        ir('perfil.php?clave=1');
    }
    ir('panel.php');
}

// 3) contraseña mal: se cuenta el intento y a los 5 se bloquea 15 minutos
if ($u) {
    $intentos = (int)$u['intentos_fallidos'] + 1;
    if ($intentos >= 5) {
        consulta("UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id_usuario=?", "i", $u['id_usuario']);
    } else {
        consulta("UPDATE usuarios SET intentos_fallidos=? WHERE id_usuario=?", "ii", $intentos, $u['id_usuario']);
    }
}
aviso('Usuario o contraseña incorrectos');
ir('login.php');
