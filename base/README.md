# Actualizaciones de Base de Datos - CMCO Citas

En esta carpeta (`base/` o `database/`) se encuentran los scripts de actualización y parches incrementales para la base de datos de producción `cmco_citas_db`.

### ¿Cómo actualizar la base de datos en el servidor del Centro Médico?

#### Opción 1: Desde el Navegador Web (Más fácil y rápido)
1. Abre tu navegador web en la máquina del servidor o en red local.
2. Ingresa a la URL:
   `http://localhost/CMCO-Citas/base/actualizar_bd.php` (o `http://localhost/CMCO-Citas/database/actualizar_bd.php`)
3. Verás una pantalla de verificación con cheques verdes confirmando que todas las columnas y colores han sido agregados con éxito.
4. Haz clic en "Ir a la Agenda".

#### Opción 2: Desde la Consola de Comandos (PowerShell / CMD)
```powershell
C:\xampp\php\php.exe C:\xampp\htdocs\CMCO-Citas\base\actualizar_bd.php
```

#### Opción 3: Ejecutando el archivo SQL directamente en MySQL
Puedes importar los archivos dentro de `base/updates/` o `database/updates/` mediante phpMyAdmin o desde la consola de MySQL:
```powershell
C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db < C:\xampp\htdocs\CMCO-Citas\database\updates\001_colores_y_adicionales.sql
```
