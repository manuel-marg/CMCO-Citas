# AGENTS.md - Contexto Permanente, Arquitectura y Reglas del Proyecto

Este archivo contiene las directrices fijas, arquitectura técnica, estilo de código y reglas universales que cualquier asistente o agente debe cumplir al desarrollar y mantener el sistema **CMCO-Citas** (Sistema de Control de Citas, Facturación y Liquidación Médica del Centro Médico Comunitario OscaryAnny).

---

## 1. Información General del Proyecto
* **Nombre:** CMCO-Citas (Control de Citas Médicas y Liquidación)
* **Organización:** Centro Médico Comunitario OscaryAnny (CMCO) / Asociación Dios Habla (ADH).
* **Objetivo:** Digitalizar el flujo de trabajo operativo que operaba sobre hojas de cálculo de Excel durante más de un año, conservando la disposición visual de 3 consultorios simultáneos, cálculo de honorarios médicos, registro de pagos mixtos multimoneda (USD / Bs a tasa BCV) y control de beneficios.
* **Ubicación en Servidor:** `C:\xampp\htdocs\CMCO-Citas\`
* **Acceso Local:** `http://localhost/CMCO-Citas/`

---

## 2. Pila Tecnológica y Entorno de Ejecución
* **Sistema Operativo:** Windows 10/11 con suite XAMPP.
* **Servidor Web:** Apache 2.4.
* **Lenguaje:** **PHP 8.2+** (PHP nativo con arquitectura limpia MVC/Modular, sin sobrecarga de frameworks externos para garantizar tiempos de respuesta inferiores a 100ms en el hardware local).
* **Base de Datos:** **MariaDB / MySQL 10.4+** (`cmco_citas_db`, motor InnoDB, juego de caracteres `utf8mb4_unicode_ci`).
* **Frontend:**
  * HTML5 semántico + CSS3 responsive.
  * Bootstrap 5.3 (descargado localmente en `/assets/vendor/` para garantizar funcionamiento sin conexión a internet).
  * Bootstrap Icons.
  * JavaScript Vanilla (ES6+) con `fetch()` asíncrono para agendamiento, cobro y cambios de estado en tiempo real.

---

## 3. Rutas y Comandos Esenciales de XAMPP
* **Ejecutable PHP:** `C:\xampp\php\php.exe`
* **Cliente MySQL:** `C:\xampp\mysql\bin\mysql.exe`
* **Herramienta de Backup MySQL:** `C:\xampp\mysql\bin\mysqldump.exe`
* **Comando para ejecutar scripts PHP desde consola:**
  ```powershell
  C:\xampp\php\php.exe script.php
  ```
* **Comando para conectar a MySQL:**
  ```powershell
  C:\xampp\mysql\bin\mysql.exe -u root cmco_citas_db
  ```

---

## 4. Estructura de Directorios del Proyecto
```
c:\xampp\htdocs\CMCO-Citas\
├── AGENTS.md                  # Contexto permanente y directrices (este archivo)
├── MEMORY.md                  # Memoria evolutiva y reglas de negocio
├── SKILLS.md                  # Habilidades y comandos de operación bajo demanda
├── config/
│   ├── database.php           # Conexión Singleton PDO segura
│   └── app.php                # Constantes de configuración, rutas y parámetros
├── controllers/
│   ├── CitaController.php     # Gestión de agenda y turnos
│   ├── PacienteController.php # Búsqueda predictiva y CRUD de pacientes
│   ├── PagoController.php     # Registro de pagos mixtos y facturación
│   ├── MedicoController.php   # Catálogo y liquidación de honorarios
│   └── BcvController.php      # Consulta y almacenamiento de tasa cambiaria
├── models/
│   ├── Cita.php
│   ├── Paciente.php
│   ├── Medico.php
│   ├── Pago.php
│   └── TasaCambio.php
├── views/
│   ├── layouts/
│   │   ├── header.php
│   │   ├── footer.php
│   │   └── navbar.php
│   ├── agenda/
│   │   ├── index.php          # Matriz visual de los 3 consultorios en simultáneo
│   │   └── modals/            # Modales de agendar, editar, cambiar estado y cobrar
│   ├── liquidacion/
│   │   └── index.php          # Reporte de honorarios médicos (Cálculo AE-AL)
│   ├── beneficios/
│   │   └── index.php          # Reporte de Empleados, Familiares y Participantes (BA-BN)
│   └── pacientes/
│       └── index.php          # Directorio y ficha del paciente
├── api/                       # Endpoints AJAX que devuelven JSON
│   ├── citas.php
│   ├── pacientes.php
│   ├── pagos.php
│   └── bcv.php
├── database/
│   ├── schema.sql             # Estructura DDL completa
│   ├── seeds.sql              # Datos iniciales (médicos, consultorios, formas de pago)
│   └── migrate_excel.py       # Script de migración de datos desde el Excel
└── assets/
    ├── css/
    ├── js/
    └── vendor/
```

---

## 5. Estándares de Código y Buenas Prácticas

### PHP Backend
1. **Seguridad SQL:** Uso estricto de **PDO con Sentencias Preparadas** (`prepare()` y `execute()`). Jamás concatenar variables directamente en las consultas SQL.
2. **Tipado y Retorno:** Declarar tipos estrictos (`declare(strict_types=1);`) en controladores y modelos.
3. **Manejo de Errores:** Bloques `try-catch` capturando `PDOException` y `Exception`. Las APIs deben responder siempre un formato JSON estándar:
   ```json
   {
     "success": true,
     "data": {},
     "message": "Operación exitosa"
   }
   ```
4. **Montos y Divisas:**
   * En MySQL: usar `DECIMAL(10,2)` para precios y montos.
   * La tasa BCV debe almacenarse como `DECIMAL(12,4)` para no perder precisión en la conversión de Bolívares.

### JavaScript Frontend
1. **Experiencia de Usuario Ininterrumpida:** Las acciones del usuario (agendar cita, cambiar estado a 💲 o 📦, registrar pago) deben realizarse por AJAX sin recargar la pantalla completa.
2. **Búsqueda Instantánea:** Implementar *autocomplete* o *debounced search* (mínimo 2 letras) para buscar pacientes por nombre o cédula.
3. **Offline First:** No utilizar dependencias mediante CDNs externos que requieran internet para cargar estilos o fuentes básicas.

---

## 6. Reglas Universales de Comunicación
* **Idioma:** Responder y documentar siempre en **Español**.
* **Precisión Médica y Financiera:** En liquidaciones y cobros, nunca asumir porcentajes sin validar las fórmulas de negocio establecidas en `MEMORY.md`.
* **Preservación:** No modificar archivos fuera del alcance sin notificar al usuario.
