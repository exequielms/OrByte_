USE orbyte_hospital;

ALTER TABLE usuarios
    ADD COLUMN correo VARCHAR(150) NULL UNIQUE AFTER apellido,
    ADD COLUMN numero_funcionario VARCHAR(20) NULL UNIQUE AFTER correo,
    ADD COLUMN departamento VARCHAR(100) NOT NULL DEFAULT 'Administracion' AFTER numero_funcionario,
    ADD COLUMN estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa',
    ADD COLUMN foto VARCHAR(60) NULL,
    ADD COLUMN ultimo_login DATETIME NULL,
    ADD COLUMN ultima_ip VARCHAR(45) NULL,
    ADD COLUMN contrasena_cambiada DATETIME NULL,
    ADD COLUMN cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN intentos_fallidos INT NOT NULL DEFAULT 0,
    ADD COLUMN bloqueado_hasta DATETIME NULL,
    ADD COLUMN version_sesion INT NOT NULL DEFAULT 1,
    ADD COLUMN creado_por INT NULL,
    ADD COLUMN fecha_creacion DATETIME NULL,
    ADD CONSTRAINT fk_usuarios_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario);

CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY, fecha_hora DATETIME NOT NULL, id_actor INT NULL, accion VARCHAR(40) NOT NULL,
    id_objetivo INT NULL, detalle VARCHAR(255), ip VARCHAR(45),
    FOREIGN KEY (id_actor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_objetivo) REFERENCES usuarios(id_usuario) ON DELETE SET NULL);

UPDATE usuarios SET fecha_creacion = NOW(), contrasena_cambiada = NOW();
UPDATE usuarios SET correo = 'cmartinez@hc.edu.uy', departamento = 'Informatica (DTI)' WHERE usuario = 'cmartinez';
UPDATE usuarios SET correo = 'mgarcia@hc.edu.uy', departamento = 'Recepcion' WHERE usuario = 'mgarcia';
UPDATE usuarios SET correo = 'alopez@hc.edu.uy', departamento = 'Archivo y Documentacion' WHERE usuario = 'alopez';
UPDATE usuarios SET correo = 'rfernandez@hc.edu.uy', departamento = 'Transportes' WHERE usuario = 'rfernandez';
UPDATE usuarios SET correo = 'lrodriguez@hc.edu.uy', departamento = 'Direccion' WHERE usuario = 'lrodriguez';
