-- CMCO-Citas Database Schema
-- Centro Médico Comunitario OscaryAnny / ADH

CREATE DATABASE IF NOT EXISTS `cmco_citas_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cmco_citas_db`;

-- 1. Tabla de Consultorios
CREATE TABLE IF NOT EXISTS `consultorios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(50) NOT NULL UNIQUE,
    `numero` INT NOT NULL,
    `descripcion` VARCHAR(150) NULL,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Tabla de Médicos / Especialistas
CREATE TABLE IF NOT EXISTS `medicos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL UNIQUE,
    `especialidad` VARCHAR(100) DEFAULT 'Medicina General',
    `telefono` VARCHAR(30) NULL,
    `porcentaje_honorario` DECIMAL(5,2) DEFAULT 50.00,
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Tabla de Pacientes
CREATE TABLE IF NOT EXISTS `pacientes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(120) NOT NULL,
    `cedula` VARCHAR(30) NULL,
    `telefono` VARCHAR(30) NULL,
    `representante` VARCHAR(120) NULL,
    `tipo_beneficio` VARCHAR(50) DEFAULT 'Particular',
    `observaciones` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_nombre` (`nombre`),
    INDEX `idx_cedula` (`cedula`)
) ENGINE=InnoDB;

-- 4. Tabla de Tasas de Cambio (BCV)
CREATE TABLE IF NOT EXISTS `tasas_cambio` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL UNIQUE,
    `tasa_usd_ves` DECIMAL(12,4) NOT NULL,
    `fecha_valor` DATE NOT NULL,
    `fuente` VARCHAR(50) DEFAULT 'BCV',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 5. Tabla de Citas Médicas
CREATE TABLE IF NOT EXISTS `citas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fecha` DATE NOT NULL,
    `hora` TIME NOT NULL,
    `consultorio_id` INT NOT NULL,
    `medico_id` INT NOT NULL,
    `paciente_id` INT NOT NULL,
    `estado` VARCHAR(50) DEFAULT 'Agendada',
    `tipo` VARCHAR(50) DEFAULT 'Particular',
    `precio_usd` DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    `tasa_bcv_aplicada` DECIMAL(12,4) NULL,
    `factura_o_empleado` VARCHAR(100) NULL,
    `motivo_consulta` VARCHAR(255) NULL,
    `observaciones` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`consultorio_id`) REFERENCES `consultorios`(`id`) ON UPDATE CASCADE,
    FOREIGN KEY (`medico_id`) REFERENCES `medicos`(`id`) ON UPDATE CASCADE,
    FOREIGN KEY (`paciente_id`) REFERENCES `pacientes`(`id`) ON UPDATE CASCADE,
    INDEX `idx_fecha_consultorio` (`fecha`, `consultorio_id`),
    INDEX `idx_fecha_medico` (`fecha`, `medico_id`),
    INDEX `idx_estado` (`estado`),
    INDEX `idx_tipo` (`tipo`)
) ENGINE=InnoDB;

-- 6. Tabla de Pagos de Citas (Soporte para pagos mixtos)
CREATE TABLE IF NOT EXISTS `pagos_citas` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cita_id` INT NOT NULL,
    `numero_pago` TINYINT DEFAULT 1 COMMENT '1 para Forma 1, 2 para Forma 2',
    `forma_pago` VARCHAR(50) NOT NULL COMMENT 'Efectivo ($), Punto de venta, Pago Movil Mercantil, Tran. BNC ($), Tran. Mercantil (Bs), Zelle',
    `monto_usd` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `monto_bs` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    `tasa_cambio` DECIMAL(12,4) NOT NULL,
    `referencia` VARCHAR(100) NULL,
    `fecha_pago` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`cita_id`) REFERENCES `citas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. Tabla de Usuarios del Sistema (Para control de acceso por roles)
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `nombre` VARCHAR(100) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `rol` ENUM('Administrador', 'Recepcionista', 'Caja', 'Medico') DEFAULT 'Recepcionista',
    `activo` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Consultorios iniciales
INSERT INTO `consultorios` (`nombre`, `numero`) VALUES
('Consultorio N° 1', 1),
('Consultorio N° 2', 2),
('Consultorio N° 3', 3)
ON DUPLICATE KEY UPDATE `numero` = VALUES(`numero`);

-- Médicos del catálogo inicial
INSERT INTO `medicos` (`nombre`, `especialidad`, `porcentaje_honorario`) VALUES
('Dr. Carlos Eduardo Márquez', 'Especialista', 50.00),
('Dr. Jorge Valeri', 'Cardiología', 50.00),
('Dra. Conchita Sánchez', 'Medicina General', 50.00),
('Dra. Evelyn Tranchino', 'Medicina General', 50.00),
('Dra. Gabriela Berbis', 'Pediatría', 50.00),
('Dra. Gerlly Mendoza', 'Medicina General', 50.00),
('Dra. Ma.Auxiliadora Aguilera', 'Especialista', 50.00),
('Dra. Maribel Guilarte', 'Medicina General', 50.00),
('Dra. Mónica Porras', 'Medicina General', 50.00),
('Dra. Rosanny García', 'Medicina General', 50.00),
('Luzmir Fagúndez', 'Terapia / Especialista', 50.00),
('Odo. Geraldine Cabrera', 'Odontología', 50.00)
ON DUPLICATE KEY UPDATE `porcentaje_honorario` = VALUES(`porcentaje_honorario`);

-- Usuario administrador por defecto (cmco_admin / admin123)
-- Hash generado para 'admin123' con password_hash('admin123', PASSWORD_BCRYPT)
INSERT INTO `usuarios` (`nombre`, `username`, `password_hash`, `rol`) VALUES
('Administrador CMCO', 'admin', '$2y$10$wK5wRj6tVlqW.8pB0eKxReG0nNqD/tqK1Y4Fh6iR5yCg4P1eS6iZa', 'Administrador')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
