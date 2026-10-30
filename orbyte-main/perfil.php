<?php
// Perfil del usuario: sus datos, foto, cambio de contraseña y seguridad de la cuenta
require 'includes/base.php';
entrar(ROLES_TODOS, true);   // true = también entra quien debe cambiar su contraseña inicial
header('Cache-Control: no-store');

$u = buscar_uno("SELECT u.nombre, u.apellido, u.usuario, u.correo, u.numero_funcionario, u.departamento, u.rol, u.estado, u.intentos_fallidos,
                        IFNULL(DATE_FORMAT(u.ultimo_login, '%d/%m/%Y %H:%i'), '-') AS ultimo,
                        IFNULL(u.ultima_ip, '-') AS ip,
                        IFNULL(DATE_FORMAT(u.contrasena_cambiada, '%d/%m/%Y %H:%i'), 'Nunca (contraseña inicial)') AS clave_cambiada,
                        IFNULL(DATE_FORMAT(u.fecha_creacion, '%d/%m/%Y %H:%i'), '-') AS creada,
                        IFNULL(CONCAT(c.nombre, ' ', c.apellido), 'el sistema') AS creador
                 FROM usuarios u LEFT JOIN usuarios c ON c.id_usuario=u.creado_por
                 WHERE u.id_usuario=?", "i", $_SESSION['usuario']['id']);

// estos dos pueden ser NULL
$correo = $u['correo'];
if (!$correo) {
    $correo = 'No registrado';
}
$numero = $u['numero_funcionario'];
if (!$numero) {
    $numero = 'No asignado';
}

// módulos a los que tiene acceso (todos los del menú menos el Panel)
$modulos = [];
foreach (menu() as $nombre => $url) {
    if ($nombre != 'Panel') {
        $modulos[] = $nombre;
    }
}
$permisos = PERMISOS[$u['rol']] ?? [];

// roles que puede administrar (solo Administrador y Supervisora)
$gestiona = '';
if (isset(PUEDEN_CREAR[$u['rol']])) {
    $gestiona = implode(', ', PUEDEN_CREAR[$u['rol']]);
}

// si debe cambiar la contraseña inicial, el formulario aparece abierto
$forzar = !empty($_SESSION['cambiar']);
$abrir_clave = '';
if ($forzar || isset($_GET['clave'])) {
    $abrir_clave = 'open';
}

// ingreso anterior (se guardó en la sesión al loguearse)
$anterior_fecha = $_SESSION['anterior']['fecha'] ?? '-';
$anterior_ip = $_SESSION['anterior']['ip'] ?? '-';

$titulo = 'Perfil';
include 'includes/cabecera.php';
?>
<h1>Perfil</h1>
<?php if ($forzar) { ?><div class="nota">Por seguridad, debe cambiar la contraseña inicial antes de usar el sistema.</div><?php } ?>

<div class="perfil-cabecera">
    <div class="avatar-grande">
        <?php if ($_SESSION['usuario']['foto']) { ?>
            <img src="foto.php?v=<?= e(substr($_SESSION['usuario']['foto'], 0, 8)) ?>" alt="Foto de perfil">
        <?php } else { ?>
            <span><?= e($_SESSION['usuario']['iniciales']) ?></span>
        <?php } ?>
    </div>
    <div>
        <h2><?= e($u['nombre']) ?> <?= e($u['apellido']) ?></h2>
        <p><?= e($u['usuario']) ?> - <?= e($u['rol']) ?></p>
        <span class="estado estado-<?= e($u['estado']) ?>">Cuenta <?= e($u['estado']) ?></span>
    </div>
</div>

<h2>Mis datos</h2>
<table class="tabla">
    <tr><th>Nombre y apellido</th><td><?= e($u['nombre']) ?> <?= e($u['apellido']) ?></td></tr>
    <tr><th>Nombre de usuario</th><td><?= e($u['usuario']) ?></td></tr>
    <tr><th>Correo institucional</th><td><?= e($correo) ?></td></tr>
    <tr><th>Número de funcionario</th><td><?= e($numero) ?></td></tr>
    <tr><th>Cargo o función en el sistema</th><td><?= e($u['rol']) ?></td></tr>
    <tr><th>Departamento o área</th><td><?= e($u['departamento']) ?></td></tr>
    <tr><th>Estado de la cuenta</th><td><span class="estado estado-<?= e($u['estado']) ?>"><?= e($u['estado']) ?></span></td></tr>
</table>

<details class="desplegable">
    <summary class="boton">Editar mis datos</summary>
    <form method="post" action="guardar_perfil.php">
        <?php csrf(); ?><input type="hidden" name="accion" value="datos">
        <div class="dos-col">
            <label>Nombre <input type="text" name="nombre" maxlength="100" required value="<?= e($u['nombre']) ?>"></label>
            <label>Apellido <input type="text" name="apellido" maxlength="100" required value="<?= e($u['apellido']) ?>"></label>
        </div>
        <button class="boton">Guardar datos</button>
    </form>
    <form method="post" action="guardar_perfil.php" enctype="multipart/form-data">
        <?php csrf(); ?><input type="hidden" name="accion" value="foto">
        <label>Foto de perfil (JPG, PNG o WebP, hasta 2 MB) <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" required></label>
        <button class="boton">Guardar foto</button>
    </form>
    <?php if ($_SESSION['usuario']['foto']) { ?>
    <form method="post" action="guardar_perfil.php" class="inline" data-confirmar="¿Quitar la foto de perfil?">
        <?php csrf(); ?><input type="hidden" name="accion" value="quitar_foto"><button class="eliminar">Quitar mi foto actual</button>
    </form>
    <?php } ?>
    <p><small>El usuario, el correo, el número de funcionario, el departamento y el rol los gestiona un administrador.</small></p>
</details>

<h2>Acceso y permisos</h2>
<h3>Módulos a los que tiene acceso</h3>
<ul class="chips"><?php foreach ($modulos as $m) { ?><li><?= e($m) ?></li><?php } ?></ul>
<h3>Permisos según su rol (<?= e($u['rol']) ?>)</h3>
<ul class="chips"><?php foreach ($permisos as $p) { ?><li><?= e($p) ?></li><?php } ?></ul>
<?php if ($gestiona != '') { ?><p><small>Puede crear y administrar usuarios con estos roles: <?= e($gestiona) ?>.</small></p><?php } ?>

<h2 id="clave">Cambiar contraseña</h2>
<details class="desplegable" <?= $abrir_clave ?>>
    <summary class="boton">Cambiar contraseña</summary>
    <form method="post" action="guardar_perfil.php" data-clave>
        <?php csrf(); ?><input type="hidden" name="accion" value="clave">
        <label>Contraseña actual <input type="password" name="actual" autocomplete="current-password" required placeholder=" "></label>
        <label>Nueva contraseña
            <input type="password" name="nueva" autocomplete="new-password" minlength="10" maxlength="72" required placeholder=" "
                   pattern="(?=.*[A-Za-z])(?=.*[0-9]).{10,72}" title="Al menos 10 caracteres, con letras y números">
        </label>
        <label>Repetir la nueva contraseña <input type="password" name="repetir" autocomplete="new-password" minlength="10" maxlength="72" required placeholder=" "></label>
        <p class="ayuda">Use al menos 10 caracteres, con letras y números. No use su nombre de usuario. Al cambiarla, se cierran sus otras sesiones.</p>
        <button class="boton">Guardar nueva contraseña</button>
    </form>
</details>

<h2>Seguridad de la cuenta</h2>
<table class="tabla">
    <tr><th>Último inicio de sesión</th><td><?= e($u['ultimo']) ?> (desde <?= e($u['ip']) ?>)</td></tr>
    <tr><th>Inicio de sesión anterior</th><td><?= e($anterior_fecha) ?> (desde <?= e($anterior_ip) ?>)</td></tr>
    <tr><th>Último cambio de contraseña</th><td><?= e($u['clave_cambiada']) ?></td></tr>
    <tr><th>Cuenta creada</th><td><?= e($u['creada']) ?>, por <?= e($u['creador']) ?></td></tr>
    <tr><th>Intentos fallidos de ingreso</th><td><?= e($u['intentos_fallidos']) ?> (se bloquea 15 minutos al llegar a 5)</td></tr>
</table>
<ul class="consejos">
    <li>No comparta su contraseña: cada funcionario tiene su propia cuenta.</li>
    <li>Si usa una computadora compartida, cierre la sesión al terminar.</li>
    <li>Si ve un inicio de sesión que no reconoce, cambie su contraseña y cierre las otras sesiones.</li>
</ul>
<div class="acciones-cuenta">
    <form method="post" action="guardar_perfil.php" class="inline" data-confirmar="Se cerrarán todas las demás sesiones abiertas de su cuenta. ¿Continuar?">
        <?php csrf(); ?><input type="hidden" name="accion" value="cerrar_otras"><button class="boton secundario">Cerrar otras sesiones</button>
    </form>
    <form method="post" action="logout.php" class="inline">
        <?php csrf(); ?><button class="boton">Cerrar sesión</button>
    </form>
</div>
<?php include 'includes/pie.php'; ?>
