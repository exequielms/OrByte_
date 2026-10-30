CREATE DATABASE IF NOT EXISTS orbyte_hospital CHARACTER SET utf8mb4;
USE orbyte_hospital;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY, usuario VARCHAR(50) NOT NULL UNIQUE, contrasena VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL, apellido VARCHAR(100) NOT NULL,
    correo VARCHAR(150) NULL UNIQUE, numero_funcionario VARCHAR(20) NULL UNIQUE, departamento VARCHAR(100) NOT NULL DEFAULT 'Administracion',
    rol ENUM('Administrador','Supervisora','Archivista','Recepcionista','Coordinador Transportes') NOT NULL,
    estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa', foto VARCHAR(60) NULL,
    ultimo_login DATETIME NULL, ultima_ip VARCHAR(45) NULL, contrasena_cambiada DATETIME NULL, cambiar_contrasena TINYINT(1) NOT NULL DEFAULT 0,
    intentos_fallidos INT NOT NULL DEFAULT 0, bloqueado_hasta DATETIME NULL, version_sesion INT NOT NULL DEFAULT 1,
    creado_por INT NULL, fecha_creacion DATETIME NULL,
    FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario));

CREATE TABLE auditoria (
    id_auditoria INT AUTO_INCREMENT PRIMARY KEY, fecha_hora DATETIME NOT NULL, id_actor INT NULL, accion VARCHAR(40) NOT NULL,
    id_objetivo INT NULL, detalle VARCHAR(255), ip VARCHAR(45),
    FOREIGN KEY (id_actor) REFERENCES usuarios(id_usuario) ON DELETE SET NULL,
    FOREIGN KEY (id_objetivo) REFERENCES usuarios(id_usuario) ON DELETE SET NULL);

CREATE TABLE documentos (
    id_documento INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(200) NOT NULL, descripcion TEXT, archivo VARCHAR(100) NOT NULL,
    categoria VARCHAR(50) NOT NULL, fecha_alta DATETIME NOT NULL, id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario));

CREATE TABLE encuestas (
    id_encuesta INT AUTO_INCREMENT PRIMARY KEY, titulo VARCHAR(200) NOT NULL, descripcion TEXT, fecha_creacion DATETIME NOT NULL,
    estado ENUM('Activa','Inactiva') NOT NULL DEFAULT 'Activa', id_usuario INT NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario));

-- al borrar una pregunta se borran solas sus opciones y respuestas (ON DELETE CASCADE)
CREATE TABLE preguntas (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY, texto TEXT NOT NULL, tipo ENUM('cerrada','abierta') NOT NULL, id_encuesta INT NOT NULL,
    FOREIGN KEY (id_encuesta) REFERENCES encuestas(id_encuesta) ON DELETE CASCADE);

CREATE TABLE opciones_respuesta (
    id_opcion INT AUTO_INCREMENT PRIMARY KEY, texto VARCHAR(200) NOT NULL, id_pregunta INT NOT NULL,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas(id_pregunta) ON DELETE CASCADE);

CREATE TABLE respuestas (
    id_respuesta INT AUTO_INCREMENT PRIMARY KEY, id_encuesta INT NOT NULL, fecha_hora DATETIME NOT NULL,
    FOREIGN KEY (id_encuesta) REFERENCES encuestas(id_encuesta) ON DELETE CASCADE);

CREATE TABLE respuestas_encuesta (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY, id_respuesta INT NOT NULL, id_pregunta INT NOT NULL, id_opcion INT NULL, respuesta_texto TEXT NULL,
    FOREIGN KEY (id_respuesta) REFERENCES respuestas(id_respuesta) ON DELETE CASCADE,
    FOREIGN KEY (id_pregunta) REFERENCES preguntas(id_pregunta) ON DELETE CASCADE,
    FOREIGN KEY (id_opcion) REFERENCES opciones_respuesta(id_opcion) ON DELETE CASCADE);

CREATE TABLE ambulancias (
    id_ambulancia INT AUTO_INCREMENT PRIMARY KEY, patente VARCHAR(20) NOT NULL UNIQUE, modelo VARCHAR(100) NOT NULL, anio INT NOT NULL,
    estado ENUM('Disponible','En servicio','Mantenimiento') NOT NULL DEFAULT 'Disponible');

CREATE TABLE personal (
    id_personal INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, apellido VARCHAR(100) NOT NULL, documento VARCHAR(20) NOT NULL UNIQUE,
    cargo ENUM('Conductor','Enfermera','Auxiliar') NOT NULL, telefono VARCHAR(20), fecha_ingreso DATE NOT NULL);

CREATE TABLE rutas (
    id_ruta INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(200) NOT NULL, origen VARCHAR(200) NOT NULL, destino VARCHAR(200) NOT NULL,
    distancia_km DECIMAL(8,2) NOT NULL, tiempo_estimado_min INT NOT NULL);

CREATE TABLE traslados (
    id_traslado INT AUTO_INCREMENT PRIMARY KEY, id_ambulancia INT NOT NULL, id_conductor INT NOT NULL, id_copiloto INT NULL, id_usuario INT NOT NULL,
    paciente_elemento VARCHAR(300) NOT NULL, tipo_elemento ENUM('Paciente','Material biologico','Equipamiento','Insumo') NOT NULL,
    punto_origen VARCHAR(300) NOT NULL, destino VARCHAR(300) NOT NULL, id_ruta INT NULL,
    hora_salida DATETIME NULL, hora_estimada_llegada DATETIME NULL, hora_efectiva_llegada DATETIME NULL, hora_regreso DATETIME NULL,
    estado ENUM('Pendiente','En curso','Llegado','Finalizado','Cancelado') NOT NULL DEFAULT 'Pendiente',
    observaciones TEXT, fecha_traslado DATE NOT NULL,
    FOREIGN KEY (id_ambulancia) REFERENCES ambulancias(id_ambulancia), FOREIGN KEY (id_conductor) REFERENCES personal(id_personal),
    FOREIGN KEY (id_copiloto) REFERENCES personal(id_personal), FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
    FOREIGN KEY (id_ruta) REFERENCES rutas(id_ruta));

CREATE TABLE seguimiento (
    id_seguimiento INT AUTO_INCREMENT PRIMARY KEY, id_traslado INT NOT NULL, id_usuario INT NOT NULL, estado VARCHAR(20) NOT NULL,
    fecha_hora DATETIME NOT NULL, comentario TEXT,
    FOREIGN KEY (id_traslado) REFERENCES traslados(id_traslado) ON DELETE CASCADE, FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario));

-- DATOS DE PRUEBA (usuarios y contrasenas en el README)
INSERT INTO usuarios (usuario, contrasena, nombre, apellido, rol) VALUES
('D.House', '$2y$12$fQ7uGDJVB.1quhI0Tz.hwuRAoerrH8VBRheimvnGZGaksAb.jiokC', 'Gregory', 'House', 'Administrador'),
('D.Chase', '$2y$12$9dKVHYwZiUpTO1BF/ijO5uQE8FbT3z6VuhutTtt4U3re0ll/8XDli', 'Robert', 'Chase', 'Recepcionista'),
('D.Foreman', '$2y$12$DtErzBVXnbuEvMmHaBmTNOlOtqTjZSS2xy8LRbLnuhvZ6Bq7UI1L.', 'Eric', 'Foreman', 'Archivista'),
('D.Wilson', '$2y$12$8HAOsvddHkBm/ePgj5QsiOZsAjZ9IloBMDP52eEv6Y9OQSeAN2pQG', 'James', 'Wilson', 'Coordinador Transportes'),
('D.Cuddy', '$2y$12$wta3/81d.pFDtsfbkCUJOu1Sew3zVbGsTNCxxijB75.qdxGmvBh/q', 'Lisa', 'Cuddy', 'Supervisora');

UPDATE usuarios SET fecha_creacion = NOW(), contrasena_cambiada = NOW();
UPDATE usuarios SET correo = 'ghouse@hc.edu.uy', departamento = 'Informatica (DTI)' WHERE usuario = 'D.House';
UPDATE usuarios SET correo = 'rchase@hc.edu.uy', departamento = 'Recepcion' WHERE usuario = 'D.Chase';
UPDATE usuarios SET correo = 'eforeman@hc.edu.uy', departamento = 'Archivo y Documentacion' WHERE usuario = 'D.Foreman';
UPDATE usuarios SET correo = 'jwilson@hc.edu.uy', departamento = 'Transportes' WHERE usuario = 'D.Wilson';
UPDATE usuarios SET correo = 'lcuddy@hc.edu.uy', departamento = 'Direccion' WHERE usuario = 'D.Cuddy';

INSERT INTO ambulancias (patente, modelo, anio, estado) VALUES ('SAA 1234', 'Mercedes Sprinter', 2020, 'Disponible'), ('SAB 5678', 'Renault Master', 2018, 'Mantenimiento');
INSERT INTO personal (nombre, apellido, documento, cargo, telefono, fecha_ingreso) VALUES
('Juan', 'Perez', '1.234.567-8', 'Conductor', '099111222', '2019-03-01'), ('Sofia', 'Silva', '2.345.678-9', 'Enfermera', '099333444', '2021-06-15');
INSERT INTO rutas (nombre, origen, destino, distancia_km, tiempo_estimado_min) VALUES ('Montevideo - Paysandu', 'Hospital de Clinicas', 'Paysandu', 380.00, 270);

INSERT INTO encuestas (titulo, descripcion, fecha_creacion, estado, id_usuario) VALUES ('Satisfaccion del paciente', 'Ayudenos a mejorar la atencion.', NOW(), 'Activa', 1);
INSERT INTO preguntas (texto, tipo, id_encuesta) VALUES ('Como califica la atencion recibida?', 'cerrada', 1), ('Algun comentario o sugerencia?', 'abierta', 1);
INSERT INTO opciones_respuesta (texto, id_pregunta) VALUES ('Muy mala', 1), ('Mala', 1), ('Regular', 1), ('Buena', 1), ('Muy buena', 1);
