# OrByte

1. Mover el ZIP a `xampp/htdocs` y extraerlo ahí. La carpeta resultante se llama `orbyte-main`: hay que renombrarla a `orbyte` (si no, `http://localhost/orbyte/` da "Not Found").


Sistema para el Hospital de Clínicas. Tiene dos partes: documentación para pacientes (con QR y encuestas) y el seguimiento de las ambulancias con un mapa.
Está hecho solo con HTML, CSS, JavaScript y PHP. El mapa usa Leaflet y la API de Geoapify.

## Instalación (XAMPP)
1. Copiar la carpeta `orbyte` dentro de `htdocs`.
2. Iniciar Apache y MySQL.
3. Importar `database/schema.sql` en phpMyAdmin.
   - Si la base ya estaba importada con los usuarios viejos, importar `database/cambiar_usuarios.sql` (cambia los 5 usuarios de prueba).
4. Entrar a `http://localhost/orbyte/`. El QR de la pared apunta a `http://localhost/orbyte/publico.php`.

La conexión a la base está en `includes/base.php` (usuario `root` sin contraseña, solo para probar).
La clave de Geoapify está en `assets/app.js` (`CLAVE_GEOAPIFY`).

## Usuarios de prueba
| Usuario | Rol | Contraseña |
|---|---|---|
| D.House | Administrador | Contraseña2205 |
| D.Chase | Recepcionista | Contraseña2206 |
| D.Foreman | Archivista | Contraseña2207 |
| D.Wilson | Coordinador Transportes | Contraseña2208 |
| D.Cuddy | Supervisora | Contraseña2209 |

El usuario se puede escribir con mayúsculas o minúsculas (`d.house` también entra).

## Cómo está armado
Cada página es un solo `.php`: arriba va la lógica (las consultas) y abajo el HTML.
Todas empiezan con `require 'includes/base.php';` y terminan con `include 'includes/pie.php';`.

- `includes/base.php`: conexión, sesión, listas fijas (roles, estados...) y funciones de ayuda (`consulta`, `buscar_uno`, `existe`, `e`, `ir`, `aviso`, `entrar`, `menu`...).
- `includes/cabecera.php` y `includes/pie.php`: lo de arriba y lo de abajo de todas las páginas.
- Páginas: `login.php`, `panel.php`, `documentos.php`, `encuestas.php`, `indicadores.php`, `ambulancias.php`, `personal.php`, `rutas.php`, `traslados.php` (con el mapa), `traslado.php`, `usuarios.php`, `perfil.php`, `qr.php` y las públicas `publico.php`, `encuesta.php` y `pdf.php`.
- Lo que procesa formularios (por POST): `validar_login.php`, `guardar_*.php`, `estado_traslado.php`, `logout.php`.
- `assets/estilos.css`: todo el diseño. `assets/app.js`: JS general y, al final, el mapa. `assets/qr.js`: el generador de QR.
- `documentos/`: acá se guardan los PDF. Tiene un `.htaccess` para que no se pueda entrar directo, se ven con `pdf.php`.
- `diagnostico.php`: página para revisar la instalación (hay que borrarla cuando termina todo).

## Seguridad
Consultas preparadas, `e()` para escapar lo que se muestra, contraseñas con `password_hash`, token CSRF en los formularios,
control de rol en cada página, bloqueo de 15 minutos después de 5 intentos fallidos y validación de los PDF que se suben.
