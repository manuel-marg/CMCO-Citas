<!-- Modal Detalle y Cambio de Estado de Cita -->
<div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalEstadoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalEstadoLabel">
                    <i class="bi bi-info-circle me-1"></i> Detalle de la Cita
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <input type="hidden" id="estado_cita_id">
                
                <!-- Ficha Rápida -->
                <div class="mb-3 p-3 bg-light rounded-3 border">
                    <h5 class="fw-bold mb-1 text-primary" id="det_paciente_nombre">Nombre del Paciente</h5>
                    <div class="text-muted small mb-2" id="det_paciente_cedula_tel">Cédula / Teléfono</div>
                    
                    <div class="row g-2 pt-2 border-top small">
                        <div class="col-6"><strong>Hora:</strong> <span id="det_hora">08:00</span></div>
                        <div class="col-6"><strong>Consultorio:</strong> <span id="det_consultorio">N° 1</span></div>
                        <div class="col-6"><strong>Médico:</strong> <span id="det_medico">Dr.</span></div>
                        <div class="col-6"><strong>Tipo:</strong> <span id="det_tipo" class="badge bg-secondary">Particular</span></div>
                        <div class="col-6"><strong>Precio:</strong> <span id="det_precio" class="fw-bold text-success">$50.00</span></div>
                        <div class="col-6"><strong>Estado Actual:</strong> <span id="det_estado_badge" class="badge bg-primary">Agendada</span></div>
                    </div>
                    
                    <div id="det_representante_box" class="mt-2 pt-2 border-top small d-none">
                        <strong>Representante:</strong> <span id="det_representante"></span>
                    </div>

                    <div id="det_pagos_box" class="mt-2 pt-2 border-top small d-none">
                        <strong>Comprobante / Factura:</strong> <span id="det_factura" class="fw-bold"></span>
                        <div id="det_pagos_lista" class="mt-1"></div>
                    </div>
                </div>

                <!-- Botones de Cambio Rápido de Estado -->
                <h6 class="fw-bold small text-muted text-uppercase mb-2">Cambiar Estado de la Cita:</h6>
                <div class="d-grid gap-2 mb-3">
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-primary w-100 text-start btn-sm" onclick="ejecutarCambioEstado('Agendada')">
                                📌 Agendada
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-warning w-100 text-start btn-sm text-dark" onclick="ejecutarCambioEstado('Sin confirmar')">
                                📞 Sin confirmar
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-info w-100 text-start btn-sm text-dark" onclick="ejecutarCambioEstado('Confirmado')">
                                ✔️ Confirmado
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-success w-100 text-start btn-sm" onclick="ejecutarCambioEstado('Completado')">
                                ✅ Atendido / Completado
                            </button>
                        </div>
                        <div class="col-12">
                            <button type="button" class="btn btn-outline-secondary w-100 text-start btn-sm" onclick="ejecutarCambioEstado('No asistio')">
                                🚫 No asistió (Cita perdida)
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Acciones Principales -->
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-success fw-bold py-2" onclick="abrirCobroDesdeDetalle()">
                        <i class="bi bi-cash-stack me-1"></i> Cobrar / Registrar Factura (💲 / 📦)
                    </button>
                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarCitaActual()">
                        <i class="bi bi-trash3 me-1"></i> Cancelar / Eliminar esta Cita
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
