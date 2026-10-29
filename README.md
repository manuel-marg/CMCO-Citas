# CMCO-Citas

Sistema de Control de Citas Médicas, Facturación, Control de Beneficios y Liquidación de Honorarios Médicos del **Centro Médico Comunitario OscaryAnny (CMCO)** / Asociación Dios Habla (ADH).

---

## 🏥 Acerca del Proyecto

**CMCO-Citas** es una solución digital diseñada para reemplazar y modernizar el flujo operativo del centro médico, el cual operaba anteriormente sobre libros de cálculo de Excel. Integra:

- **Agenda Multivista:** Matriz interactiva de los 3 consultorios en simultáneo (08:00 a 20:00), con vistas diaria, semanal y mensual interactiva.
- **Cobro Multimoneda:** Registro de pagos divididos o mixtos (Efectivo en $, Transferencias, Pago Móvil, Zelle y Punto de Venta en Bs) con sincronización automática de la tasa oficial del Banco Central de Venezuela (BCV).
- **Semáforo y Estados de Citas:** Gestión con estados visuales (📌 Agendada, 📞 Sin confirmar, ✔️ Confirmado, ✅ Completado, 🚫 No asistió, 💲 Pagado, 📦 Facturado).
- **Liquidación Automática de Médicos:** Cálculo según el modelo 50% ADH / 50% Médico Especialista, compensación de beneficios (empleados, familiares, exonerados) y exportación directa a reportes PDF en orientación horizontal.
- **Control de Beneficios:** Seguimiento detallado de consultas de empleados, participantes y familiares directos.
- **Directorio de Pacientes:** Búsqueda predictiva instantánea y gestión de fichas médicas.

---

## 🛠️ Pila Tecnológica

- **Backend:** PHP 8.2+ nativo con arquitectura limpia MVC/Modular (tiempos de respuesta inferiores a 100ms en red local).
- **Base de Datos:** MySQL / MariaDB 10.4+ (`cmco_citas_db`, InnoDB, `utf8mb4_unicode_ci`).
- **Frontend:** HTML5, CSS3 responsive, Bootstrap 5.3 + Bootstrap Icons (incluidos localmente para soporte *Offline-First*), JavaScript ES6+ asíncrono con `fetch()`.
- **Reportes:** FPDF integrado para generación nativa de documentos PDF en orientación horizontal (Letter Landscape).
- **Servidor Web:** Apache 2.4 (entorno XAMPP en Windows).

---

## 🚀 Instalación y Puesta en Marcha

1. **Clonar el Repositorio en el directorio `htdocs` de XAMPP:**
   ```bash
   cd C:\xampp\htdocs\
   git clone https://github.com/manuel-marg/CMCO-Citas.git
   ```

2. **Crear e Importar la Base de Datos:**
   - Inicia Apache y MySQL desde el Panel de Control de XAMPP.
   - Entra en phpMyAdmin (`http://localhost/phpmyadmin`) o desde la consola MySQL:
     ```bash
     C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS cmco_citas_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
     C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db < database/schema.sql
     C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db < database/imported_data.sql
     ```

3. **Acceder a la Aplicación:**
   - Abre tu navegador web e ingresa a:
     ```
     http://localhost/CMCO-Citas/
     ```
   - O desde otra terminal en la red local:
     ```
     http://<IP-DEL-SERVIDOR>/CMCO-Citas/
     ```

---

## 📄 Licencia y Derechos

Desarrollado para el **Centro Médico Comunitario OscaryAnny (CMCO)** y la **Asociación Dios Habla (ADH)**. Todos los derechos reservados.
