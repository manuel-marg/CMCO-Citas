-- Migración 002: Soporte para tipo de reparto en procedimientos adicionales (ej: Citología 100% Centro ADH vs Procedimientos 50/50)
SET @dbname = DATABASE();
SET @tablename = "citas";
SET @columnname = "adicional_reparto";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  "ALTER TABLE citas ADD COLUMN adicional_reparto VARCHAR(30) DEFAULT 'compartido' AFTER costo_laboratorio;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Actualizar citas previas que contengan 'citolog' en el concepto para que el neto sea 100% del centro
UPDATE citas 
SET adicional_reparto = 'centro' 
WHERE LOWER(COALESCE(adicional_concepto, '')) LIKE '%citolog%';
