<!-- Modal Detalle y Cambio de Estado de Cita (Diseño Compacto y Simétrico) -->
<div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalEstadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content shadow border-0">
            <!-- Header Compacto -->
            <div class="modal-header py-2 px-3 text-white" style="background: linear-gradient(135deg, #1d4353 0%, #387387 100%);">
                <h6 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" id="modalEstadoLabel">
                    <i class="bi bi-calendar2-check text-info"></i> Detalle de la Cita
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-3">
                <input type="hidden" id="estado_cita_id">
                
                <!-- Ficha Rápida Ejecutiva y Compacta -->
                <div class="p-2.5 px-3 bg-light rounded-3 border mb-2.5">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <div class="text-truncate">
                            <h6 class="fw-bold mb-0 text-primary text-truncate" id="det_paciente_nombre">Nombre del Paciente</h6>
                            <div class="text-muted small" style="font-size: 0.78rem;" id="det_paciente_cedula_tel">C.I / Teléfono</div>
                        </div>
                        <div class="text-end text-nowrap">
                            <span id="det_precio" class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-6 px-2 py-0.5">$50.00</span>
                            <div class="mt-1">
                                <span id="det_estado_badge" class="badge bg-primary" style="font-size: 0.72rem;">📌 Agendada</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-1 pt-1.5 border-top small" style="font-size: 0.8rem;">
                        <div class="col-6 text-truncate">
                            <i class="bi bi-clock me-1 text-primary"></i><strong>Hora:</strong> <span id="det_hora" class="text-dark">08:00 AM</span>
                        </div>
                        <div class="col-6 text-truncate">
                            <i class="bi bi-door-closed me-1 text-primary"></i><strong>Ubicación:</strong> <span id="det_consultorio" class="text-dark">C1</span>
                        </div>
                        <div class="col-6 text-truncate">
                            <i class="bi bi-person-badge me-1 text-primary"></i><strong>Médico:</strong> <span id="det_medico" class="text-dark">Dr.</span>
                        </div>
                        <div class="col-6 text-truncate">
                            <i class="bi bi-tag me-1 text-primary"></i><strong>Tipo:</strong> <span id="det_tipo" class="badge bg-white text-dark border py-0.5">Particular</span>
                        </div>
                    </div>
                    
                    <div id="det_representante_box" class="pt-1 mt-1 border-top small text-truncate d-none" style="font-size: 0.78rem;">
                        <i class="bi bi-people me-1 text-muted"></i><strong>Representante:</strong> <span id="det_representante" class="text-dark"></span>
                    </div>

                    <div id="det_pagos_box" class="pt-1 mt-1 border-top small d-none" style="font-size: 0.78rem;">
                        <div class="text-truncate">
                            <i class="bi bi-receipt me-1 text-success"></i><strong>Factura:</strong> <span id="det_factura" class="fw-semibold text-dark"></span>
                        </div>
                        <div id="det_pagos_lista" class="mt-0.5 text-muted small"></div>
                    </div>
                </div>

                <!-- Botones de Cambio Rápido de Estado con Simetría Perfecta -->
                <div class="mb-2.5">
                    <div class="d-flex justify-content-between align-items-center mb-1.5">
                        <span class="fw-bold small text-muted text-uppercase" style="font-size: 0.72rem; letter-spacing: 0.5px;">
                            Cambiar Estado:
                        </span>
                    </div>
                    <div class="row g-1.5">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100 py-1.5 px-2 text-start text-truncate fw-semibold" onclick="ejecutarCambioEstado('Agendada')">
                                📌 Agendada
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-warning btn-sm w-100 py-1.5 px-2 text-start text-truncate text-dark fw-semibold" onclick="ejecutarCambioEstado('Sin confirmar')">
                                📞 Sin confirmar
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-info btn-sm w-100 py-1.5 px-2 text-start text-truncate text-dark fw-semibold" onclick="ejecutarCambioEstado('Confirmado')">
                                ✔️ Confirmado
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success btn-sm w-100 py-1.5 px-2 text-start text-truncate fw-semibold" onclick="ejecutarCambioEstado('Atendido')">
                                ✅ Atendido
                            </button>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 py-1 px-2 text-center text-truncate" onclick="ejecutarCambioEstado('No asistio')">
                                🚫 No asistió
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Acciones Principales en 1 Sola Fila Balanceada -->
                <div class="d-flex gap-2 pt-2 border-top">
                    <button type="button" class="btn btn-outline-danger btn-sm px-3 text-nowrap" onclick="eliminarCitaActual()" title="Cancelar y eliminar esta cita">
                        <i class="bi bi-trash3 me-1"></i> Eliminar
                    </button>
                    <button type="button" class="btn btn-success fw-bold btn-sm flex-grow-1 py-2 text-truncate" onclick="abrirCobroDesdeDetalle()">
                        <i class="bi bi-cash-stack me-1"></i> Cobrar / Facturar (💲 / 📦)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
