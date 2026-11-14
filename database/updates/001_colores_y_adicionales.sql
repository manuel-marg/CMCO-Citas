-- =========================================================================
-- CMCO-Citas: Script de Actualización de Base de Datos
-- Archivo: 001_colores_y_adicionales.sql
-- Propósito: Incorporar campos de color para médicos y procedimientos adicionales/laboratorio en citas
-- Seguridad: Idempotente (Seguro para ejecutar sobre bases de datos en producción con datos reales)
-- =========================================================================

USE `cmco_citas_db`;

-- 1. Agregar columna 'color' en tabla 'medicos' (si no existe aún)
SET @col_med_color = 0;
SELECT COUNT(*) INTO @col_med_color 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'medicos' 
  AND COLUMN_NAME = 'color';

SET @sql_med_color = IF(@col_med_color = 0, 
    'ALTER TABLE `medicos` ADD COLUMN `color` VARCHAR(20) DEFAULT ''#4f90a5'' AFTER `especialidad`;', 
    'SELECT "Columna color ya existe en tabla medicos";');
PREPARE stmt1 FROM @sql_med_color;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- 2. Asignar colores históricos de la validación del sistema anterior a los especialistas
UPDATE `medicos` SET `color` = '#3c3c3c' WHERE `nombre` LIKE '%Carlos Eduardo M%';
UPDATE `medicos` SET `color` = '#d0edb7' WHERE `nombre` LIKE '%Jorge Valeri%';
UPDATE `medicos` SET `color` = '#e6cbf4' WHERE `nombre` LIKE '%Conchita S%';
UPDATE `medicos` SET `color` = '#175b68' WHERE `nombre` LIKE '%Evelyn Tranchino%';
UPDATE `medicos` SET `color` = '#f8b3a3' WHERE `nombre` LIKE '%Gabriela Berbis%';
UPDATE `medicos` SET `color` = '#ffe5b8' WHERE `nombre` LIKE '%Gerlly Mendoza%';
UPDATE `medicos` SET `color` = '#ffe699' WHERE `nombre` LIKE '%Ma.Auxiliadora%';
UPDATE `medicos` SET `color` = '#ffcbc1' WHERE `nombre` LIKE '%Maribel Guilarte%';
UPDATE `medicos` SET `color` = '#c1dce3' WHERE `nombre` LIKE '%Mónica Porras%' OR `nombre` LIKE '%Monica Porras%';
UPDATE `medicos` SET `color` = '#e6e6e6' WHERE `nombre` LIKE '%Rosanny Garc%';
UPDATE `medicos` SET `color` = '#784516' WHERE `nombre` LIKE '%Luzmir Fag%';
UPDATE `medicos` SET `color` = '#b90602' WHERE `nombre` LIKE '%Geraldine Cabrera%';
UPDATE `medicos` SET `color` = '#0f714a' WHERE `nombre` LIKE '%Laboratorio%';

-- 3. Agregar columna 'adicional_concepto' en tabla 'citas' (si no existe aún)
SET @col_cit_conc = 0;
SELECT COUNT(*) INTO @col_cit_conc 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'citas' 
  AND COLUMN_NAME = 'adicional_concepto';

SET @sql_cit_conc = IF(@col_cit_conc = 0, 
    'ALTER TABLE `citas` ADD COLUMN `adicional_concepto` VARCHAR(150) NULL AFTER `observaciones`;', 
    'SELECT "Columna adicional_concepto ya existe en tabla citas";');
PREPARE stmt2 FROM @sql_cit_conc;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- 4. Agregar columna 'adicional_monto' en tabla 'citas' (si no existe aún)
SET @col_cit_monto = 0;
SELECT COUNT(*) INTO @col_cit_monto 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'citas' 
  AND COLUMN_NAME = 'adicional_monto';

SET @sql_cit_monto = IF(@col_cit_monto = 0, 
    'ALTER TABLE `citas` ADD COLUMN `adicional_monto` DECIMAL(10,2) DEFAULT 0.00 AFTER `adicional_concepto`;', 
    'SELECT "Columna adicional_monto ya existe en tabla citas";');
PREPARE stmt3 FROM @sql_cit_monto;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- 5. Agregar columna 'costo_laboratorio' en tabla 'citas' (si no existe aún)
SET @col_cit_lab = 0;
SELECT COUNT(*) INTO @col_cit_lab 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'citas' 
  AND COLUMN_NAME = 'costo_laboratorio';

SET @sql_cit_lab = IF(@col_cit_lab = 0, 
    'ALTER TABLE `citas` ADD COLUMN `costo_laboratorio` DECIMAL(10,2) DEFAULT 0.00 AFTER `adicional_monto`;', 
    'SELECT "Columna costo_laboratorio ya existe en tabla citas";');
PREPARE stmt4 FROM @sql_cit_lab;
EXECUTE stmt4;
DEALLOCATE PREPARE stmt4;
