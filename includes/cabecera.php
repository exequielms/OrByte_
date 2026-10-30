<?php
// Parte de arriba de todas las páginas: head, encabezado con el menú y la línea del monitor.
// Cada página tiene que definir $titulo antes de incluirla.
?>
<!DOCTYPE html>
<html lang="es" data-tema="claro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
    <script>try { document.documentElement.dataset.tema = localStorage.getItem("tema") || "claro"; } catch (e) {}</script>
    <link rel="stylesheet" href="assets/estilos.css">
</head>
<body>
<header class="encabezado">
    <a class="marca" href="index.php">
        <img class="logo logo-claro" src="assets/logo-claro.png" alt="OrByte">
        <img class="logo logo-oscuro" src="assets/logo-oscuro.png" alt="OrByte">
        <small>Hospital de Clínicas</small>
    </a>
    <?php if (isset($_SESSION['usuario'])) { ?>
    <nav>
        <?php foreach (menu() as $texto_menu => $enlace_menu) { ?>
        <a href="<?= e($enlace_menu) ?>"><?= e($texto_menu) ?></a>
        <?php } ?>
        <a class="perfil-icono" href="perfil.php" title="Perfil de <?= e($_SESSION['usuario']['nombre']) ?>" aria-label="Perfil de <?= e($_SESSION['usuario']['nombre']) ?>">
            <?php if ($_SESSION['usuario']['foto']) { ?>
                <img src="foto.php?v=<?= e(substr($_SESSION['usuario']['foto'], 0, 8)) ?>" alt="">
            <?php } else { ?>
                <span><?= e($_SESSION['usuario']['iniciales']) ?></span>
            <?php } ?>
        </a>
    </nav>
    <?php } ?>
    <button type="button" id="tema" class="tema" aria-label="Cambiar entre modo claro y oscuro">
        <svg class="sol" viewBox="0 0 24 24"><path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0-5h0a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zm0 17a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1zM2 12a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2H3a1 1 0 0 1-1-1zm17 0a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2h-2a1 1 0 0 1-1-1z"/></svg>
        <svg class="luna" viewBox="0 0 24 24"><path d="M21 13.5A9 9 0 0 1 10.5 3a9 9 0 1 0 10.5 10.5z"/></svg>
        <span class="txt-oscuro">Modo oscuro</span>
        <span class="txt-claro">Modo claro</span>
    </button>
</header>
<div class="monitor" aria-hidden="true">
    <svg viewBox="0 0 1200 60" preserveAspectRatio="none">
        <defs>
            <linearGradient id="barrido" gradientUnits="userSpaceOnUse" x1="-400" y1="0" x2="0" y2="0">
                <stop offset="0" class="tono" stop-opacity="0"/>
                <stop offset="0.85" class="tono" stop-opacity="1"/>
                <stop offset="1" class="tono" stop-opacity="0"/>
                <animate attributeName="x1" values="-400;1200" dur="4s" repeatCount="indefinite"/>
                <animate attributeName="x2" values="0;1600" dur="4s" repeatCount="indefinite"/>
            </linearGradient>
        </defs>
        <path class="base" d="M0 30 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40"/>
        <path class="pulso" stroke="url(#barrido)" d="M0 30 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40 h50 l10 -8 l10 8 h14 l6 6 l8 -36 l10 52 l8 -22 h24 q10 -12 20 0 h40"/>
    </svg>
</div>
<main>
<?php
// avisos y errores: se muestran una vez y se borran de la sesión
if (isset($_SESSION['aviso'])) {
    echo '<div class="mensaje">' . e($_SESSION['aviso']) . '</div>';
    unset($_SESSION['aviso']);
}
if (isset($_SESSION['errores'])) {
    echo '<div class="nota error"><ul>';
    foreach ($_SESSION['errores'] as $texto) {
        echo '<li>' . e($texto) . '</li>';
    }
    echo '</ul></div>';
    unset($_SESSION['errores']);
}
?>
