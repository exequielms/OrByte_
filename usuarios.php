<?php
// Usuarios (solo Administrador y Supervisora)
require 'includes/base.php';
entrar(array_keys(PUEDEN_CREAR));
header('Cache-Control: no-store');

$yo = $_SESSION['usuario'];

// contraseña temporal recién generada y lo que se había escrito si hubo errores (una sola vez)
$temporal = $_SESSION['clave_temporal'] ?? null;
$viejo = $_SESSION['form_usuario'] ?? [];
unset($_SESSION['clave_temporal'], $_SESSION['form_usuario']);

$lista = consulta("SELECT u.id_usuario, u.usuario, CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo, IFNULL(u.correo, '-') AS correo,
                          u.departamento, u.rol, u.estado, u.cambiar_contrasena,
                          IFNULL(DATE_FORMAT(u.ultimo_login, '%d/%m/%Y %H:%i'), 'Nunca') AS ultimo,
                          IFNULL(CONCAT(c.nombre, ' ', c.apellido), 'Sistema') AS creador,
                          IFNULL(DATE_FORMAT(u.fecha_creacion, '%d/%m/%Y %H:%i'), '-') AS creado
                   FROM usuarios u LEFT JOIN usuarios c ON c.id_usuario=u.creado_por
                   ORDER BY u.estado, u.apellido, u.nombre");

// texto para mostrar de cada acción de la auditoria
$etiquetas = [
    'crear_usuario' => 'Creó un usuario',
    'estado_usuario' => 'Cambió el estado de una cuenta',
    'restablecer_clave' => 'Restableció una contraseña',
    'cambio_clave' => 'Cambió su contraseña',
    'cerrar_otras_sesiones' => 'Cerró otras sesiones',
    'intento_no_autorizado' => 'Intento no autorizado',
];
$actividad = consulta("SELECT DATE_FORMAT(a.fecha_hora, '%d/%m/%Y %H:%i') AS fecha, a.accion,
                              IFNULL(CONCAT(x.nombre, ' ', x.apellido), '-') AS actor, IFNULL(y.usuario, '-') AS objetivo, IFNULL(a.detalle, '') AS detalle
                       FROM auditoria a
                       LEFT JOIN usuarios x ON x.id_usuario=a.id_actor
                       LEFT JOIN usuarios y ON y.id_usuario=a.id_objetivo
                       ORDER BY a.id_auditoria DESC LIMIT 15");

// valores del form de crear usuario (vacíos o los de antes si hubo error)
$f_nombre = $viejo['nombre'] ?? '';
$f_apellido = $viejo['apellido'] ?? '';
$f_correo = $viejo['correo'] ?? '';
$f_usuario = $viejo['usuario'] ?? '';
$f_numero = $viejo['numero_funcionario'] ?? '';
$f_depto = $viejo['departamento'] ?? '';
$f_rol = $viejo['rol'] ?? '';
$abrir_crear = '';
if (count($viejo) > 0) {
    $abrir_crear = 'open';
}

$titulo = 'Usuarios';
include 'includes/cabecera.php';
?>
<h1>Gestión de usuarios</h1>

<?php if ($temporal) { ?>
<div class="credenciales">
    <h2>Credenciales iniciales de <?= e($temporal['nombre']) ?></h2>
    <p>Usuario: <code><?= e($temporal['usuario']) ?></code></p>
    <p>Contraseña temporal: <code><?= e($temporal['clave']) ?></code></p>
    <p><small>Se muestra una sola vez. Entréguela en forma personal. La persona deberá cambiarla al ingresar por primera vez.</small></p>
</div>
<?php } ?>

<table class="tabla">
    <tr><th>Funcionario</th><th>Usuario</th><th>Departamento</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Creada por</th><th>Acciones</th></tr>
    <?php foreach ($lista as $f) { ?>
    <?php
        // solo a otros usuarios de rol inferior, a uno mismo no
        $puede = ($f['id_usuario'] != $yo['id'] && puede_gestionar($f['rol']));
    ?>
    <tr>
        <td><?= e($f['nombre_completo']) ?><br><small><?= e($f['correo']) ?></small></td>
        <td><?= e($f['usuario']) ?></td><td><?= e($f['departamento']) ?></td><td><?= e($f['rol']) ?></td>
        <td><span class="estado estado-<?= e($f['estado']) ?>"><?= e($f['estado']) ?></span><?php if ($f['cambiar_contrasena']) { ?><br><small>Debe cambiar su contraseña</small><?php } ?></td>
        <td><?= e($f['ultimo']) ?></td>
        <td><?= e($f['creador']) ?><br><small><?= e($f['creado']) ?></small></td>
        <td>
            <?php if ($puede) { ?>
            <form method="post" action="guardar_usuario.php" class="inline" data-confirmar="¿Cambiar el estado de esta cuenta? Si la desactiva, se cierran sus sesiones.">
                <?php csrf(); ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_usuario" value="<?= e($f['id_usuario']) ?>"><button class="enlace"><?= $f['estado'] == 'Activa' ? 'Desactivar' : 'Activar' ?></button>
            </form> |
            <form method="post" action="guardar_usuario.php" class="inline" data-confirmar="¿Generar una contraseña temporal nueva? La actual deja de funcionar.">
                <?php csrf(); ?><input type="hidden" name="accion" value="restablecer"><input type="hidden" name="id_usuario" value="<?= e($f['id_usuario']) ?>"><button class="enlace">Restablecer contraseña</button>
            </form>
            <?php } else { ?>-<?php } ?>
        </td>
    </tr>
    <?php } ?>
</table>

<details class="desplegable" id="crear-usuario" <?= $abrir_crear ?>>
    <summary class="boton">+ Crear usuario</summary>
    <form method="post" action="guardar_usuario.php" autocomplete="off">
        <?php csrf(); ?><input type="hidden" name="accion" value="crear">
        <p class="ayuda">Los campos con * son obligatorios. El sistema genera una contraseña temporal que se muestra una sola vez al crear la cuenta.</p>
        <div class="dos-col">
            <label>Nombre * <input type="text" name="nombre" maxlength="100" required placeholder="Ej: María" value="<?= e($f_nombre) ?>"></label>
            <label>Apellido * <input type="text" name="apellido" maxlength="100" required placeholder="Ej: González" value="<?= e($f_apellido) ?>"></label>
            <label>Correo electrónico institucional * <input type="email" name="correo" maxlength="150" required placeholder="nombre@hc.edu.uy" value="<?= e($f_correo) ?>"></label>
            <label>Nombre de usuario * <input type="text" name="usuario" maxlength="30" minlength="3" required placeholder="mgonzalez" autocapitalize="none" pattern="[a-z0-9._\-]{3,30}" title="De 3 a 30 caracteres: letras minúsculas, números, punto, guion o guion bajo" value="<?= e($f_usuario) ?>"></label>
            <label>Número de funcionario (opcional) <input type="text" name="numero_funcionario" maxlength="20" placeholder="Ej: 4567" value="<?= e($f_numero) ?>"></label>
            <label>Departamento o área de trabajo *
                <select name="departamento" required><option value="">Seleccione</option><?php opciones(DEPARTAMENTOS, $f_depto); ?></select>
            </label>
            <label>Rol *
                <select name="rol" required><option value="">Seleccione</option><?php opciones(PUEDEN_CREAR[$yo['rol']], $f_rol); ?></select>
            </label>
        </div>
        <button class="boton">Crear usuario</button>
    </form>
</details>

<h2>Actividad reciente</h2>
<table class="tabla">
    <tr><th>Fecha</th><th>Quién</th><th>Acción</th><th>Sobre</th><th>Detalle</th></tr>
    <?php foreach ($actividad as $a) { ?>
    <?php
        $accion = $a['accion'];
        if (isset($etiquetas[$accion])) {
            $accion = $etiquetas[$accion];
        }
    ?>
    <tr><td><?= e($a['fecha']) ?></td><td><?= e($a['actor']) ?></td><td><?= e($accion) ?></td><td><?= e($a['objetivo']) ?></td><td><?= e($a['detalle']) ?></td></tr>
    <?php } ?>
    <?php if (count($actividad) == 0) { ?><tr><td colspan="5">Todavía no hay actividad registrada.</td></tr><?php } ?>
</table>
<?php include 'includes/pie.php'; ?>
