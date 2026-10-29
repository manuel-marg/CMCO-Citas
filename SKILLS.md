# SKILLS.md - Habilidades y Flujos de Trabajo Bajo Demanda

Este archivo contiene procedimientos paso a paso, comandos directos y recetas modulares que el agente o desarrollador puede consultar y ejecutar cuando se requiera realizar tareas específicas en el sistema **CMCO-Citas**.

---

## Índice de Habilidades
1. [`skill-db-setup-and-migrate`](#1-skill-db-setup-and-migrate) - Crear Base de Datos y Migrar Pacientes/Médicos desde Excel
2. [`skill-run-dev-server`](#2-skill-run-dev-server) - Verificación y Arranque de Servicios XAMPP
3. [`skill-bcv-update`](#3-skill-bcv-update) - Actualización de la Tasa Oficial del Dólar BCV
4. [`skill-doctor-settlement-query`](#4-skill-doctor-settlement-query) - Consulta SQL de Liquidación de Honorarios Médicos
5. [`skill-backup-restore`](#5-skill-backup-restore) - Respaldos y Restauración de Base de Datos
6. [`skill-lan-configuration`](#6-skill-lan-configuration) - Configuración para Acceso en Red Local de la Clínica

---

### 1. `skill-db-setup-and-migrate`
**Propósito:** Inicializar el esquema relacional en MariaDB/MySQL y migrar los 628 pacientes y médicos del Excel a la base de datos `cmco_citas_db`.

**Paso a Paso:**
1. Crear la base de datos ejecutando el script DDL:
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cmco_citas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db < C:\xampp\htdocs\CMCO-Citas\database\schema.sql
   ```
2. Ejecutar el script de migración de datos desde el Excel:
   ```powershell
   python C:\xampp\htdocs\CMCO-Citas\database\migrate_excel.py
   ```
3. Verificar la cantidad de registros insertados:
   ```powershell
   C:\xampp\mysql\bin\mysql.exe -u root -e "SELECT COUNT(*) AS total_pacientes FROM cmco_citas_db.pacientes; SELECT COUNT(*) AS total_medicos FROM cmco_citas_db.medicos;"
   ```

---

### 2. `skill-run-dev-server`
**Propósito:** Asegurar que Apache y MySQL estén activos y verificar que el sistema responda en el puerto local.

**Comandos:**
* Comprobar proceso de Apache:
  ```powershell
  Get-Process httpd -ErrorAction SilentlyContinue
  ```
* Comprobar proceso de MySQL:
  ```powershell
  Get-Process mysqld -ErrorAction SilentlyContinue
  ```
* Probar respuesta HTTP del sistema:
  ```powershell
  Invoke-WebRequest -Uri "http://localhost/CMCO-Citas/" -UseBasicParsing | Select-Object StatusCode, StatusDescription
  ```

---

### 3. `skill-bcv-update`
**Propósito:** Actualizar la tasa de cambio USD/VES oficial y la fecha valor en la tabla `tasas_cambio`.

**Implementación en Backend (`api/bcv.php`):**
* Realiza una petición `cURL` a `https://www.bcv.org.ve/` extrayendo el elemento con id `#dolar` y la fecha valor.
* Si el sitio del BCV presenta captcha o lentitud, permite registrar la tasa manualmente desde la barra superior de la aplicación:
  ```sql
  INSERT INTO tasas_cambio (fecha, tasa_usd_ves, fecha_valor, fuente)
  VALUES (CURDATE(), 888.8909, CURDATE(), 'Manual/BCV')
  ON DUPLICATE KEY UPDATE tasa_usd_ves = VALUES(tasa_usd_ves), fecha_valor = VALUES(fecha_valor);
  ```

---

### 4. `skill-doctor-settlement-query`
**Propósito:** Obtener la liquidación consolidada por médico para un rango de fechas dado (ej. un mes completo), reflejando exactamente las fórmulas del Excel (`AE:AL`).

**Consulta SQL Maestra:**
```sql
SELECT 
    m.id AS medico_id,
    m.nombre AS medico,
    COUNT(CASE WHEN c.estado IN ('💲', '📦', '✅') THEN 1 END) AS cantidad_citas,
    COUNT(CASE WHEN c.estado IN ('💲', '📦') THEN 1 END) AS citas_pagadas,
    COALESCE(SUM(CASE WHEN c.estado IN ('💲', '📦') THEN c.precio_usd ELSE 0 END), 0) AS total_bruto,
    
    -- Monto Cobrado en caja: Particular + 50% Familiar + 50% Exonerado ADH + 50% Exonerado Doctor
    COALESCE(SUM(CASE 
        WHEN c.estado IN ('💲', '📦') AND c.tipo = 'Particular' THEN c.precio_usd
        WHEN c.estado IN ('💲', '📦') AND c.tipo IN ('Familiar', 'Exonerado ADH', 'Exonerado Doctor') THEN c.precio_usd * 0.5
        ELSE 0 
    END), 0) AS monto_cobrado,
    
    -- Monto ADH (Centro Médico): 50% Particular + 50% Exonerado Doctor
    COALESCE(SUM(CASE 
        WHEN c.estado IN ('💲', '📦') AND c.tipo = 'Particular' THEN c.precio_usd * 0.5
        WHEN c.estado IN ('💲', '📦') AND c.tipo = 'Exonerado Doctor' THEN c.precio_usd * 0.5
        ELSE 0 
    END), 0) AS monto_adh,
    
    -- Monto Médico: 50% sobre Particular, Familiar, Participante, Empleado, Exonerado ADH
    COALESCE(SUM(CASE 
        WHEN c.estado IN ('💲', '📦') AND c.tipo IN ('Particular', 'Familiar', 'Participante', 'Empleado', 'Exonerado ADH') THEN c.precio_usd * 0.5
        ELSE 0 
    END), 0) AS monto_medico

FROM medicos m
LEFT JOIN citas c ON c.medico_id = m.id AND c.fecha BETWEEN :fecha_inicio AND :fecha_fin
WHERE m.activo = 1
GROUP BY m.id, m.nombre
ORDER BY m.nombre ASC;
```

---

### 5. `skill-backup-restore`
**Propósito:** Generar una copia de seguridad íntegra de la base de datos o restaurarla en caso de mantenimiento.

* **Exportar Backup:**
  ```powershell
  $date = Get-Date -Format "yyyyMMdd_HHmmss"
  C:\xampp\mysql\bin\mysqldump.exe -u root cmco_citas_db > "C:\xampp\htdocs\CMCO-Citas\database\backups\cmco_backup_$date.sql"
  ```
* **Restaurar Backup:**
  ```powershell
  C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db < "C:\xampp\htdocs\CMCO-Citas\database\backups\cmco_backup_archivo.sql"
  ```

---

### 6. `skill-lan-configuration`
**Propósito:** Habilitar el acceso para las recepcionistas, médicos y administradores desde otras computadoras de la red local (LAN) del centro médico.

**Procedimiento:**
1. Obtener la IP local de la computadora servidor:
   ```powershell
   Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.InterfaceAlias -like "*Wi-Fi*" -or $_.InterfaceAlias -like "*Ethernet*" } | Select-Object IPAddress, InterfaceAlias
   ```
2. Abrir el puerto 80 en el Firewall de Windows para la red privada:
   ```powershell
   New-NetFirewallRule -DisplayName "Apache XAMPP CMCO" -Direction Inbound -LocalPort 80 -Protocol TCP -Action Allow -Profile Private
   ```
3. Desde cualquier otra computadora, tableta o teléfono conectado al WiFi o red cableada de la clínica, ingresar en el navegador:
   `http://<IP_LOCAL>/CMCO-Citas/` (Ejemplo: `http://192.168.1.150/CMCO-Citas/`).
