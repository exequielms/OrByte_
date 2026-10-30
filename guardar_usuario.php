<?php
// Crear usuarios, activarlos o desactivarlos y restablecer contraseñas (POST)
require 'includes/base.php';
entrar(array_keys(PUEDEN_CREAR));
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('usuarios.php');
}

$yo = $_SESSION['usuario'];
$accion = $_POST['accion'] ?? '';

// ---- crear usuario ----
if ($accion == 'crear') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = strtolower(trim($_POST['correo'] ?? ''));
    $usuario = strtolower(trim($_POST['usuario'] ?? ''));
    $numero = trim($_POST['numero_funcionario'] ?? '');
    $depto = $_POST['departamento'] ?? '';
    $rol = $_POST['rol'] ?? '';

    // se guarda lo escrito en la sesión para no perderlo si hay errores
    $_SESSION['form_usuario'] = ['nombre' => $nombre, 'apellido' => $apellido, 'correo' => $correo, 'usuario' => $usuario,
                                 'numero_funcionario' => $numero, 'departamento' => $depto, 'rol' => $rol];

    // validaciones: cada error se suma a $err
    $err = [];
    $patron_nombre = '/^\p{L}[\p{L} .\'-]{0,99}$/u';

    if ($nombre == '' || $apellido == '' || $correo == '' || $usuario == '' || $depto == '' || $rol == '') {
        $err[] = "Complete todos los campos obligatorios.";
    }
    if ($nombre != '' && !preg_match($patron_nombre, $nombre)) {
        $err[] = "El nombre debe tener solo letras (hasta 100 caracteres).";
    }
    if ($apellido != '' && !preg_match($patron_nombre, $apellido)) {
        $err[] = "El apellido debe tener solo letras (hasta 100 caracteres).";
    }
    if ($correo != '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 150)) {
        $err[] = "El correo electrónico no es válido.";
    }
    if ($correo != '' && DOMINIO_CORREO != '' && !str_ends_with($correo, '@' . DOMINIO_CORREO)) {
        $err[] = "El correo debe ser institucional (@" . DOMINIO_CORREO . ").";
    }
    if ($usuario != '' && !preg_match('/^[a-z0-9._-]{3,30}$/', $usuario)) {
        $err[] = "El nombre de usuario debe tener de 3 a 30 caracteres: letras minúsculas, números, punto, guion o guion bajo.";
    }
    if ($numero != '' && !preg_match('/^[0-9A-Za-z.-]{1,20}$/', $numero)) {
        $err[] = "El número de funcionario solo puede tener letras, números, punto o guion (hasta 20).";
    }
    if ($depto != '' && !in_array($depto, DEPARTAMENTOS, true)) {
        $err[] = "El departamento elegido no es valido.";
    }
    if ($rol != '' && !in_array($rol, ROLES_TODOS, true)) {
        $err[] = "El rol elegido no es valido.";
    } elseif ($rol != '' && !puede_gestionar($rol)) {
        // quiso crear un rol que no le corresponde, queda anotado en la auditoria
        $err[] = "No tiene autorización para crear usuarios con el rol " . $rol . ".";
        auditar('intento_no_autorizado', null, "Intentó crear un usuario con rol $rol");
    }

    // que no esté repetido
    if (count($err) == 0) {
        $repetidos = consulta("SELECT usuario, correo, numero_funcionario FROM usuarios
                               WHERE usuario=? OR correo=? OR (?<>'' AND numero_funcionario=?)", "ssss", $usuario, $correo, $numero, $numero);
        foreach ($repetidos as $x) {
            if ($x['usuario'] == $usuario) {
                $err[] = "Ya existe un usuario con el nombre de usuario \"$usuario\".";
            }
            if ($x['correo'] == $correo) {
                $err[] = "Ya existe un usuario con ese correo electrónico.";
            }
            if ($numero != '' && $x['numero_funcionario'] == $numero) {
                $err[] = "Ya existe un usuario con ese número de funcionario.";
            }
        }
    }

    if (count($err) > 0) {
        errores(array_unique($err));
        aviso("No se pudo crear el usuario. Revise los datos.");
        ir('usuarios.php#crear-usuario');
    }

    // se crea con contraseña temporal y la persona la cambia al entrar
    $clave = clave_temporal();
    $numero_guardar = null;
    if ($numero != '') {
        $numero_guardar = $numero;
    }
    $nuevo = consulta("INSERT INTO usuarios (usuario, contrasena, nombre, apellido, correo, numero_funcionario, departamento, rol, estado,
                                             cambiar_contrasena, version_sesion, creado_por, fecha_creacion)
                       VALUES (?,?,?,?,?,?,?,?,'Activa',1,1,?,NOW())",
                      "ssssssssi", $usuario, password_hash($clave, PASSWORD_DEFAULT), $nombre, $apellido, $correo,
                      $numero_guardar, $depto, $rol, $yo['id']);
    auditar('crear_usuario', $nuevo, "Rol $rol, departamento $depto");
    unset($_SESSION['form_usuario']);
    $_SESSION['clave_temporal'] = ['usuario' => $usuario, 'nombre' => "$nombre $apellido", 'clave' => $clave];
    aviso("Usuario creado correctamente.");
    ir('usuarios.php');
}

// ---- activar / desactivar y restablecer contraseña ----
if ($accion == 'estado' || $accion == 'restablecer') {
    $id = (int)($_POST['id_usuario'] ?? 0);
    $objetivo = buscar_uno("SELECT id_usuario, usuario, nombre, apellido, rol, estado FROM usuarios WHERE id_usuario=?", "i", $id);

    if (!$objetivo) {
        aviso("El usuario no existe.");
        ir('usuarios.php');
    }
    if ($objetivo['id_usuario'] == $yo['id']) {
        aviso("No puede hacer esto sobre su propia cuenta. Use su Perfil.");
        ir('usuarios.php');
    }
    if (!puede_gestionar($objetivo['rol'])) {
        auditar('intento_no_autorizado', $id, "Intentó $accion sobre rol " . $objetivo['rol']);
        aviso("No tiene autorización para gestionar a un usuario con rol " . $objetivo['rol'] . ".");
        ir('usuarios.php');
    }

    if ($accion == 'estado') {
        // pasa de Activa a Inactiva o al revés; version_sesion + 1 le cierra las sesiones
        $estado_nuevo = 'Activa';
        if ($objetivo['estado'] == 'Activa') {
            $estado_nuevo = 'Inactiva';
        }
        consulta("UPDATE usuarios SET estado=?, version_sesion=version_sesion+1 WHERE id_usuario=?", "si", $estado_nuevo, $id);
        auditar('estado_usuario', $id, "Estado: $estado_nuevo");
        aviso("La cuenta de " . $objetivo['usuario'] . " ahora está " . strtolower($estado_nuevo) . ".");
    } else {
        // contraseña temporal nueva; también se desbloquea la cuenta
        $clave = clave_temporal();
        consulta("UPDATE usuarios SET contrasena=?, cambiar_contrasena=1, contrasena_cambiada=NULL, intentos_fallidos=0,
                                      bloqueado_hasta=NULL, version_sesion=version_sesion+1 WHERE id_usuario=?",
                 "si", password_hash($clave, PASSWORD_DEFAULT), $id);
        auditar('restablecer_clave', $id, 'Contraseña temporal nueva');
        $_SESSION['clave_temporal'] = ['usuario' => $objetivo['usuario'], 'nombre' => $objetivo['nombre'] . ' ' . $objetivo['apellido'], 'clave' => $clave];
        aviso("Contraseña restablecida.");
    }
    ir('usuarios.php');
}

ir('usuarios.php');
