-- Para una base que YA estaba importada: reemplaza los 5 usuarios de prueba por los nuevos.
-- Importar en phpMyAdmin (base orbyte_hospital). Los datos de los usuarios nuevos estan en el README.
USE orbyte_hospital;

UPDATE usuarios SET usuario = 'D.House', contrasena = '$2y$12$fQ7uGDJVB.1quhI0Tz.hwuRAoerrH8VBRheimvnGZGaksAb.jiokC', nombre = 'Gregory', apellido = 'House', correo = 'ghouse@hc.edu.uy', departamento = 'Informatica (DTI)', rol = 'Administrador',
    estado = 'Activa', cambiar_contrasena = 0, intentos_fallidos = 0, bloqueado_hasta = NULL, contrasena_cambiada = NOW(), version_sesion = version_sesion + 1
    WHERE usuario = 'cmartinez';

UPDATE usuarios SET usuario = 'D.Chase', contrasena = '$2y$12$9dKVHYwZiUpTO1BF/ijO5uQE8FbT3z6VuhutTtt4U3re0ll/8XDli', nombre = 'Robert', apellido = 'Chase', correo = 'rchase@hc.edu.uy', departamento = 'Recepcion', rol = 'Recepcionista',
    estado = 'Activa', cambiar_contrasena = 0, intentos_fallidos = 0, bloqueado_hasta = NULL, contrasena_cambiada = NOW(), version_sesion = version_sesion + 1
    WHERE usuario = 'mgarcia';

UPDATE usuarios SET usuario = 'D.Foreman', contrasena = '$2y$12$DtErzBVXnbuEvMmHaBmTNOlOtqTjZSS2xy8LRbLnuhvZ6Bq7UI1L.', nombre = 'Eric', apellido = 'Foreman', correo = 'eforeman@hc.edu.uy', departamento = 'Archivo y Documentacion', rol = 'Archivista',
    estado = 'Activa', cambiar_contrasena = 0, intentos_fallidos = 0, bloqueado_hasta = NULL, contrasena_cambiada = NOW(), version_sesion = version_sesion + 1
    WHERE usuario = 'alopez';

UPDATE usuarios SET usuario = 'D.Wilson', contrasena = '$2y$12$8HAOsvddHkBm/ePgj5QsiOZsAjZ9IloBMDP52eEv6Y9OQSeAN2pQG', nombre = 'James', apellido = 'Wilson', correo = 'jwilson@hc.edu.uy', departamento = 'Transportes', rol = 'Coordinador Transportes',
    estado = 'Activa', cambiar_contrasena = 0, intentos_fallidos = 0, bloqueado_hasta = NULL, contrasena_cambiada = NOW(), version_sesion = version_sesion + 1
    WHERE usuario = 'rfernandez';

UPDATE usuarios SET usuario = 'D.Cuddy', contrasena = '$2y$12$wta3/81d.pFDtsfbkCUJOu1Sew3zVbGsTNCxxijB75.qdxGmvBh/q', nombre = 'Lisa', apellido = 'Cuddy', correo = 'lcuddy@hc.edu.uy', departamento = 'Direccion', rol = 'Supervisora',
    estado = 'Activa', cambiar_contrasena = 0, intentos_fallidos = 0, bloqueado_hasta = NULL, contrasena_cambiada = NOW(), version_sesion = version_sesion + 1
    WHERE usuario = 'lrodriguez';
