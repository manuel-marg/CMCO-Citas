<!-- SECCIÓN: LIQUIDACIÓN DE HONORARIOS MÉDICOS -->
<section id="section-liquidacion" class="py-3 d-none">
    <div class="container-fluid px-4">
        
        <!-- Encabezado Limpio Exclusivo para Impresión (Oculto en pantalla, visible solo al imprimir) -->
        <div class="d-none d-print-block mb-3 print-header-liquidacion">
            <div class="d-flex align-items-center justify-content-between border-bottom pb-2">
                <div class="d-flex align-items-center gap-3">
                    <img src="assets/img/logo.png" alt="CMCO" style="height: 38px;">
                    <div>
                        <h3 class="h4 fw-bold text-dark mb-0" id="print_liq_titulo">Mes de Octubre de 2026</h3>
                        <small class="text-muted">Centro Médico Comunitario OscaryAnny (CMCO / ADH)</small>
                    </div>
                </div>
                <div class="text-end text-muted small">
                    <span id="print_liq_fecha"></span>
                </div>
            </div>
        </div>

        <!-- Barra de Control (Oculta al imprimir) -->
        <div class="agenda-bar mb-3 no-print">
            <div class="row g-3 align-items-center">
                <div class="col-xl-5 col-lg-5 col-md-12">
                    <h4 class="fw-bold mb-1 text-primary">
                        <i class="bi bi-calculator me-1"></i> Honorarios Médicos
                    </h4>
                    <p class="small text-muted mb-0">
                        Cálculo automático de honorarios médicos según el porcentaje.
                    </p>
                </div>
                <div class="col-xl-4 col-lg-4 col-md-6 d-flex align-items-center gap-2">
                    <label class="form-label mb-0 fw-bold small text-muted">Mes:</label>
                    <input type="month" class="form-control fw-bold border-primary" id="liq_mes_selector" onchange="cargarLiquidacion()">
                    <button class="btn btn-primary" onclick="cargarLiquidacion()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Recargar liquidación">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                </div>
                <div class="col-xl-3 col-lg-3 col-md-6 text-md-end d-flex align-items-center justify-content-md-end gap-2">
                    <button class="btn btn-primary fw-bold shadow-sm" onclick="exportarLiquidacionPDF()" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Exportar a PDF">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </button>
                    <button class="btn btn-outline-primary fw-bold shadow-sm" onclick="showSection('medicos')" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Gestionar Doctores">
                        <i class="bi bi-person-gear"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabla Resumen por Médico -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0 text-center" id="tabla_liquidacion">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-start ps-3">Médico / Especialista</th>
                                <th>Especialidad</th>
                                <th>Cantidad Citas</th>
                                <th>Citas Pagadas</th>
                                <th>Total Bruto ($)</th>
                                <th>Monto Cobrado ($)</th>
                                <th class="bg-primary text-white">Monto ADH ($)</th>
                                <th class="bg-success text-white">Monto Médico ($)</th>
                                <th class="no-print">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_liquidacion">
                            <tr>
                                <td colspan="9" class="py-4 text-muted">Cargando reporte de liquidación...</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light fw-bold fs-6" id="tfoot_liquidacion">
                            <!-- Totales inyectados por JS -->
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Detalle de Citas del Médico Seleccionado -->
        <div class="card shadow-sm border-0 d-none" id="card_detalle_medico">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-card-checklist text-primary me-2"></i> 
                    Desglose de Citas: <span id="detalle_nombre_medico" class="text-primary"></span>
                </h5>
                <button class="btn btn-sm btn-outline-secondary no-print" onclick="cerrarDetalleMedico()">
                    <i class="bi bi-x-lg"></i> Cerrar Detalle
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0 text-center">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Consultorio</th>
                                <th>Paciente</th>
                                <th>Cédula</th>
                                <th>Tipo de Cita</th>
                                <th>Estado</th>
                                <th>Precio ($)</th>
                                <th>Factura / Beneficio</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_detalle_citas">
                            <!-- Inyectado por JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</section>
