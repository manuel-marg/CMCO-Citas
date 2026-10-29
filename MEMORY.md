# MEMORY.md - Memoria Evolutiva y Reglas del Dominio

Este documento almacena el conocimiento acumulado sobre el funcionamiento del centro médico, el análisis forense realizado sobre la hoja de cálculo de Excel original, las decisiones técnicas tomadas y las lecciones aprendidas para garantizar la continuidad del proyecto.

---

## 1. Contexto del Negocio: Centro Médico Comunitario OscaryAnny (CMCO / ADH)
* **Entidad:** Centro Médico Comunitario OscaryAnny, adscrito a ADH.
* **Flujo Operativo Original:** Durante un año completo, el centro utilizó un libro de cálculo Excel de gran escala (`Centro Médico Comunitario OscaryAnny - CONTROL DE CITAS Sistema.xlsx`) con hojas mensuales (`Septiembre`, `Octubre`, `Noviembre`) y una hoja central `Base de Datos`.
* **Capacidad Instalada:**
  * **3 Consultorios en simultáneo:** Consultorio N° 1, Consultorio N° 2, Consultorio N° 3.
  * **Jornada de atención:** 13 bloques horarios de 1 hora cada uno, de **08:00 a 20:00** (8:00 AM a 8:00 PM).
  * **Capacidad máxima teórica diaria:** 39 citas por día (13 citas × 3 consultorios).

---

## 2. Catálogo de Estados y Semáforo de Citas
El personal de recepción y caja está acostumbrado a una nomenclatura de estados basada en emojis/íconos visuales:
1. 📌 **Agendada:** Cita reservada para el paciente en el horario respectivo.
2. 📞 **Sin confirmar:** La recepcionista aún no ha podido contactar al paciente.
3. ✔️ **Confirmado:** Paciente confirmó asistencia a la cita.
4. ✅ **Completado:** El médico atendió al paciente en el consultorio.
5. 🚫 **No asistió:** El paciente faltó al turno.
6. 💲 **Pagado:** El paciente canceló en recepción pero la factura formal está en proceso.
7. 📦 **Facturado:** Cita formalmente facturada y asentada en el libro de ingresos.

> **Regla de Conteo:** Las citas que se consideran formalmente efectivas para honorarios médicos son aquellas en estado **💲 (Pagado)** y **📦 (Facturado)** (y en estadísticas de asistencia se incluye **✅ Completado**).

---

## 3. Modelo Matemático de Liquidación de Honorarios Médicos

El análisis de las fórmulas de las columnas `AE` a `AL` del Excel original reveló la **lógica financiera exacta de reparto**:

### Esquema General
* **Precio Base:** Fijado en **Dólares Americanos ($ USD)**.
* **Reparto Estándar:** **50% para el Centro Médico (ADH)** y **50% para el Médico Especialista**.

### Reglas Específicas por Tipo de Consulta (`Tipo`):
1. **Particular (Estándar):**
   * Paciente paga: **100%** del precio.
   * Cobrado: 100%.
   * Reparto: **50% ADH** / **50% Médico**.
2. **Empleado (Beneficio de Empleado del Mes):**
   * Paciente paga: **$0** (Exoneración al 100%).
   * Centro Médico (ADH): Exonera su cuota (**$0** para ADH).
   * Médico: **Recibe su 50% de honorario**, el cual es asumido y pagado directamente por el Centro Médico como beneficio laboral.
3. **Participante ADH:**
   * Paciente paga: **$0**.
   * Centro Médico (ADH): Recibe **$0**.
   * Médico: **Recibe su 50% de honorario**, financiado por ADH.
4. **Familiar (Familiar Directo - 50% Descuento):**
   * Paciente paga: **50%** del precio de la consulta.
   * Centro Médico (ADH): Exonera su ganancia (**$0** para ADH).
   * Médico: **Recibe el 100% de lo cobrado** (que equivale a su 50% habitual).
5. **Exonerado ADH:**
   * El Centro Médico exonera su margen (**$0** para ADH).
   * El paciente abona el 50% destinado al Médico (**50% Médico**).
6. **Exonerado Doctor:**
   * El Médico decide no cobrar sus honorarios (**$0** para el Médico).
   * El paciente abona el 50% que ingresa al Centro Médico (**50% ADH**).

### Fórmulas Algebraicas de las Columnas del Reporte
* **Total Bruto (AI):** $\sum \text{Precio}$ para citas en estado `💲` o `📦`.
* **Monto Cobrado (AJ):** Total cobrado en caja física/banco:
  $$\text{Cobrado} = \text{Particular} + 0.5 \times \text{Familiar} + 0.5 \times \text{Exonerado ADH} + 0.5 \times \text{Exonerado Doctor}$$
* **Monto ADH (AK):**
  $$\text{Monto ADH} = 0.5 \times \text{Particular} + 0.5 \times \text{Exonerado Doctor}$$
* **Monto Médico (AL):**
  $$\text{Monto Médico} = 0.5 \times (\text{Particular} + \text{Familiar} + \text{Participante} + \text{Empleado} + \text{Exonerado ADH})$$

---

## 4. Pagos y Tasa Cambiaria (BCV)
* **Tasa Oficial:** Se utiliza la tasa del Banco Central de Venezuela (`https://www.bcv.org.ve/`), redondeada y formateada a **2 decimales** (ej: Bs. 888,89) para mayor comodidad del usuario en caja y visualización.
* **Fecha Valor:** Fecha en la cual entra en vigencia la tasa de cambio publicada.
* **Formas de Pago Registradas en el Sistema:**
  * `Efectivo ($)`
  * `Punto de venta` (en Bolívares)
  * `Pago Movil Mercantil` (en Bolívares)
  * `Tran. BNC ($)`
  * `Tran. Mercantil (Bs)`
  * `Zelle` ($)
* **Pagos Divididos (Mixtos):** Cada cita admite simultáneamente `Forma 1 + Monto 1` y `Forma 2 + Monto 2` para liquidar consultas con combinaciones (ej. $10 en efectivo + saldo en punto de venta).

---

## 5. Decisiones Técnicas Acordadas
1. **Despliegue Local en XAMPP:**
   * Alojar el sistema en `c:\xampp\htdocs\CMCO-Citas`.
   * Base de datos MySQL `cmco_citas_db`.
   * El acceso se realiza desde el navegador web de la PC principal y desde las demás terminales de la red local (mediante la IP privada de la PC servidora, ej: `http://192.168.1.50/CMCO-Citas`).
2. **Interfaz Fiel a la Experiencia de Usuario con Soporte Multi-Vista:**
   * **Vista Diaria:** Mantiene la matriz de los 3 consultorios en simultáneo (Consultorios 1, 2 y 3) de 08:00 a 20:00.
   * **Vista Semanal:** Despliega 7 columnas (Lunes a Domingo) con tarjetas ordenadas por hora, badges diferenciados por consultorio (`C1`, `C2`, `C3`) y acceso directo para agendar en cualquier día.
   * **Vista Mensual:** Cuadrícula de calendario mensual interactiva con chips de citas por día y salto instantáneo a la vista diaria al hacer clic en cualquier fecha.
   * Filtros dinámicos y métricas del período integrados en un panel desplegable bajo demanda con el botón `[ Filtrar ]`, ahorrando el máximo espacio vertical en la pantalla de la agenda.
3. **Migración Histórica:**
   * La hoja `Base de Datos` cuenta con **628 pacientes** y 68 empleados/beneficiarios. Todos migrados a la tabla `pacientes` conservando su nombre, cédula, teléfono, representante y tipo de beneficiario.
   * Catálogo de médicos inicial:
     * Dr. Carlos Eduardo Márquez
     * Dr. Jorge Valeri
     * Dra. Conchita Sánchez
     * Dra. Evelyn Tranchino
     * Dra. Gabriela Berbis
     * Dra. Gerlly Mendoza
     * Dra. Ma.Auxiliadora Aguilera
     * Dra. Maribel Guilarte
     * Dra. Mónica Porras
     * Dra. Rosanny García
     * Luzmir Fagúndez
     * Odo. Geraldine Cabrera

---

## 6. Lecciones Aprendidas y Puntos de Atención
* **Disponibilidad Offline:** Las clínicas a veces sufren micro-cortes de internet. Si el BCV no responde, el sistema debe permitir ingresar o mantener la última tasa BCV registrada en la base de datos sin trabar el cobro de la recepción.
* **Cédulas Duplicadas:** En Venezuela es común que los menores de edad no tengan cédula propia y se registre la del representante, o bien `0` o `S/C`. El campo cédula no debe ser `UNIQUE` estricto a nivel de base de datos para no bloquear el registro de niños que comparten cédula de su representante.
* **Codificación UTF-8 e Integridad de Nombres:** En Windows, el pipe estándar de PowerShell hacia `mysql.exe` procesa la entrada en codificación ANSI si no se especifica expresamente UTF-8, lo que provocaba que vocales con tilde (`á`, `é`, `í`, `ó`, `ú`, `ñ`) se convirtieran en signos de interrogación `?`. Para garantizar la integridad ortográfica del catálogo de pacientes y citas, se debe ejecutar la carga a través de conexiones PDO (`utf8mb4`) o usando el script automatizado [`database/repair_utf8.php`](file:///c:/xampp/htdocs/CMCO-Citas/database/repair_utf8.php).

---

## 7. Decisiones de Interfaz, Identidad Visual y Experiencia de Usuario (UI/UX)
* **Logotipo Institucional:** Reemplazo del emoji genérico `🏥` por el logotipo oficial de CMCO descargado desde `https://cmcoscaryanny.netlify.app/img/favicon.png` y alojado localmente en `assets/img/logo.png` y `assets/img/favicon.png`.
* **Contraste de Navbar:** Como el logo PNG tiene fondo transparente, se colocó dentro de un contenedor blanco estilizado (`.navbar-logo-box`) de **58×48px** con esquinas redondeadas (`border-radius: 8px`), borde blanco nítido (`border: 2px solid rgba(255,255,255,0.85)`) y padding ajustado para que la imagen aproveche al máximo el espacio interior (~47×41px) con excelente contraste y legibilidad sobre el degradado azul del navbar.
* **Barra de Navegación Compacta:** Pestañas simplificadas a una sola palabra clave ("Agenda", "Médicos", "Beneficios", "Pacientes").
* **Tasa BCV:** Extraída en vivo desde el sitio oficial `bcv.org.ve` (`#dolar strong`) y formateada estrictamente a 2 decimales para claridad operativa.
* **Filtros Plegables:** Controles de búsqueda y filtros agrupados bajo un botón colapsable `[ Filtrar ]` para maximizar el área visible de la matriz de consultorios.
* **Degradado del Navbar:** Configurado con un degradado armónico corporativo `linear-gradient(135deg, #1d4353 0%, #387387 50%, #4f90a5 100%)`, pasando de un tono petróleo profundo a la izquierda (que maximiza el contraste del logo y texto blanco) hasta el color corporativo `#4f90a5` hacia la derecha.
* **Paleta de Color Primario Institucional:** Sustitución global del azul genérico `#0d6efd` por el tono corporativo azul petróleo/verde azulado `#4f90a5` (RGB: 79, 144, 165), alineado con el escudo del centro médico. Afecta a botones primarios (`.btn-primary`), botones outline (`.btn-outline-primary`), insignias (`.badge.bg-primary`), bordes de tarjetas de citas agendadas y acentos activos en toda la interfaz.
* **Formato de Horas (12h con AM/PM):** Visualización universal en formato de 12 horas (`08:00 AM` a `08:00 PM`) en la columna de turnos de la vista diaria, tarjetas semanales, calendario mensual y modales operativos, corrigiendo cualquier concatenación híbrida (como `13 PM`).
* **Chips del Calendario Mensual (UI Compacta):** Presentación compacta `[Hora] [Emoji Estado] [Nombre Paciente]` donde el nombre se acorta inteligentemente a sus primeras 2 palabras (ej. "María Auxiliadora") para prevenir desbordes en las cuadrículas diarias, preservando en el atributo `title` accesible el nombre completo y el médico tratante.

---

## 8. Generación y Exportación de Reportes en PDF (Liquidación Médica)
* **Generador Nativo:** Integración local de **FPDF** en `assets/vendor/fpdf/fpdf.php` para generar archivos binarios `.pdf` ultra rápidos (<50ms) sin depender de librerías externas pesadas ni de internet (100% offline).
* **Orientación Horizontal (Landscape):** Diseñado en formato apaisado (Letter horizontal: 279.4 × 215.9 mm) con márgenes de 10 mm y ancho útil de 259.4 mm, permitiendo que las 8 columnas numéricas y de texto quepan holgadamente sin desbordar ni cortarse.
* **Diseño Ejecutivo Simplificado:**
  * Logotipo oficial CMCO en la esquina superior izquierda con separación vertical calibrada (`SetY(32)` / `margin-top: 20px`) para garantizar que la tabla nunca toque ni pise el logo.
  * Título conciso: **`Mes de [Mes] de [Año]`** (ej: *Mes de Octubre de 2026*).
  * Directamente la tabla de datos con cabeceras en azul petróleo (`#4f90a5`), filas alternadas (`#f8f9fa`) y fila de totales destacada (`#1d4353`).
  * Sin textos ruidosos ni explicaciones operativas extensas ("Liquidación de honorarios...", "Cálculo de...", etc.).
* **Acceso Dual:**
  1. **Botón "Imprimir / Exportar PDF":** Abre directamente `api/liquidacion_pdf.php?mes=YYYY-MM` en una pestaña nueva con el archivo `.pdf` listo para descargar o imprimir con el visor nativo.
  2. **Impresión Web (@media print):** Reglas CSS con `@page { size: landscape; margin: 1cm; }` que ocultan navbar, footer y selectores, mostrando un membrete limpio con el mes seleccionado.
