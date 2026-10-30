<?php
// Acciones del perfil, por POST. La acción viene en $_POST['accion'].
require 'includes/base.php';
entrar(ROLES_TODOS, true);
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ir('perfil.php');
}

$id = $_SESSION['usuario']['id'];
$accion = $_POST['accion'] ?? '';

// nombre y apellido
if ($accion == 'datos') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    // solo letras, espacios, punto, apóstrofo y guion (hasta 100)
    $patron = '/^\p{L}[\p{L} .\'-]{0,99}$/u';
    if (!preg_match($patron, $nombre) || !preg_match($patron, $apellido)) {
        errores(["El nombre y el apellido deben tener solo letras (hasta 100 caracteres)."]);
    } else {
        consulta("UPDATE usuarios SET nombre=?, apellido=? WHERE id_usuario=?", "ssi", $nombre, $apellido, $id);
        aviso("Datos actualizados.");
    }
    ir('perfil.php');
}

// foto de perfil
if ($accion == 'foto') {
    $archivo = $_FILES['foto'] ?? ['error' => 4];
    $info = false;
    if ($archivo['error'] == 0) {
        $info = @getimagesize($archivo['tmp_name']);   // false si no es una imagen
    }
    $extensiones = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    if ($archivo['error'] != 0) {
        errores(["No se pudo subir la imagen. Revise que no pase de 2 MB."]);
    } elseif ($archivo['size'] > 2 * 1024 * 1024) {
        errores(["La imagen no puede pesar más de 2 MB."]);
    } elseif (!$info || !isset($extensiones[$info[2]]) || $info[0] < 50 || $info[1] < 50 || $info[0] > 4000 || $info[1] > 4000) {
        errores(["La foto debe ser una imagen JPG, PNG o WebP (entre 50 y 4000 píxeles)."]);
    } else {
        // la carpeta fotos se crea la primera vez, con un .htaccess para que no se pueda entrar directo
        if (!is_dir(CARPETA_FOTOS)) {
            mkdir(CARPETA_FOTOS, 0755, true);
            file_put_contents(CARPETA_FOTOS . '/.htaccess', "Require all denied\n");
        }
        $nombre_nuevo = bin2hex(random_bytes(12)) . '.' . $extensiones[$info[2]];
        if (move_uploaded_file($archivo['tmp_name'], CARPETA_FOTOS . '/' . $nombre_nuevo)) {
            $viejo = buscar_uno("SELECT foto FROM usuarios WHERE id_usuario=?", "i", $id);
            consulta("UPDATE usuarios SET foto=? WHERE id_usuario=?", "si", $nombre_nuevo, $id);
            if ($viejo && $viejo['foto']) {
                @unlink(CARPETA_FOTOS . '/' . basename($viejo['foto']));
            }
            aviso("Foto de perfil actualizada.");
        } else {
            errores(["No se pudo guardar la imagen en el servidor."]);
        }
    }
    ir('perfil.php');
}

// quitar la foto
if ($accion == 'quitar_foto') {
    $viejo = buscar_uno("SELECT foto FROM usuarios WHERE id_usuario=?", "i", $id);
    consulta("UPDATE usuarios SET foto=NULL WHERE id_usuario=?", "i", $id);
    if ($viejo && $viejo['foto']) {
        @unlink(CARPETA_FOTOS . '/' . basename($viejo['foto']));
    }
    aviso("Foto de perfil eliminada.");
    ir('perfil.php');
}

// cambio de contraseña
if ($accion == 'clave') {
    $actual = (string)($_POST['actual'] ?? '');
    $nueva = (string)($_POST['nueva'] ?? '');
    $repetir = (string)($_POST['repetir'] ?? '');
    $u = buscar_uno("SELECT contrasena, usuario FROM usuarios WHERE id_usuario=?", "i", $id);

    $err = [];
    if (!password_verify($actual, $u['contrasena'])) {
        $err[] = "La contraseña actual no es correcta.";
    }
    if (strlen($nueva) < 10 || strlen($nueva) > 72) {
        $err[] = "La nueva contraseña debe tener entre 10 y 72 caracteres.";
    }
    if (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/[0-9]/', $nueva)) {
        $err[] = "La nueva contraseña debe tener letras y números.";
    }
    if ($nueva !== $repetir) {
        $err[] = "Las dos contraseñas nuevas no coinciden.";
    }
    if ($nueva !== '' && password_verify($nueva, $u['contrasena'])) {
        $err[] = "La nueva contraseña debe ser distinta de la actual.";
    }
    if ($nueva !== '' && stripos($nueva, $u['usuario']) !== false) {
        $err[] = "La contraseña no puede contener su nombre de usuario.";
    }
    if (count($err) > 0) {
        errores($err);
        ir('perfil.php?clave=1');
    }

    // al sumar 1 a version_sesion se cierran las otras sesiones de la cuenta
    consulta("UPDATE usuarios SET contrasena=?, contrasena_cambiada=NOW(), cambiar_contrasena=0, version_sesion=version_sesion+1 WHERE id_usuario=?",
             "si", password_hash($nueva, PASSWORD_DEFAULT), $id);
    $fila = buscar_uno("SELECT version_sesion FROM usuarios WHERE id_usuario=?", "i", $id);
    $_SESSION['version'] = (int)$fila['version_sesion'];   // esta sesión sigue siendo válida
    $_SESSION['cambiar'] = false;
    session_regenerate_id(true);
    auditar('cambio_clave', $id, 'Cambio de su propia contraseña');
    aviso("Contraseña actualizada. Las otras sesiones abiertas se cerraron.");
    ir('perfil.php');
}

// cerrar las otras sesiones
if ($accion == 'cerrar_otras') {
    consulta("UPDATE usuarios SET version_sesion=version_sesion+1 WHERE id_usuario=?", "i", $id);
    $fila = buscar_uno("SELECT version_sesion FROM usuarios WHERE id_usuario=?", "i", $id);
    $_SESSION['version'] = (int)$fila['version_sesion'];
    auditar('cerrar_otras_sesiones', $id, 'Cerró las demás sesiones abiertas');
    aviso("Se cerraron las demás sesiones de su cuenta.");
    ir('perfil.php');
}

ir('perfil.php');
