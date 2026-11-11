<!-- Modal de Cobro y Facturación de Cita -->
<div class="modal fade" id="modalCobrar" tabindex="-1" aria-labelledby="modalCobrarLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formCobrar" onsubmit="guardarCobro(event)">
                <input type="hidden" id="cobrar_cita_id" name="cita_id">
                
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="modalCobrarLabel">
                        <i class="bi bi-cash-coin me-1"></i> Cobro y Facturación de Cita
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Resumen de la Cita -->
                    <div class="card mb-3 bg-light border-0">
                        <div class="card-body py-2 px-3">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <h6 class="fw-bold mb-1 text-primary" id="cobrar_paciente_nombre">Paciente</h6>
                                    <div class="small text-muted" id="cobrar_info_adicional">Dr. / Consultorio / Hora</div>
                                </div>
                                <div class="col-md-5 text-md-end">
                                    <div class="fs-4 fw-bold text-success" id="cobrar_precio_display">$50.00</div>
                                    <div class="small text-muted" id="cobrar_precio_bs_display">Bs. 0,00 (Tasa BCV)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Procedimientos / Insumos Adicionales (Rayos X, Férulas, etc.) -->
                    <div class="card mb-3 border-secondary-subtle shadow-sm">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="checkAdicionales" onchange="toggleAdicionalesCobro()">
                                <label class="form-check-label fw-bold small text-dark" for="checkAdicionales">
                                    <i class="bi bi-plus-circle me-1 text-primary"></i> Procedimiento o Insumo Adicional (Rayos X, Férula, etc.)
                                </label>
                            </div>
                            <span id="badge_adicional_activo" class="badge bg-primary-subtle text-primary border border-primary-subtle d-none">
                                Con Adicional
                            </span>
                        </div>
                        <div id="container_adicionales" class="card-body p-3 bg-light-subtle d-none border-top">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted mb-1">Concepto del Adicional:</label>
                                    <input type="text" class="form-control form-control-sm" id="cobrar_adicional_concepto" name="adicional_concepto" placeholder="Ej: Rayos X, Férula de descarga, etc.">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Monto a Cobrar ($):</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold text-success" id="cobrar_adicional_monto" name="adicional_monto" value="0.00" oninput="recalcularConversionCobro()">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold text-muted mb-1">Laboratorio / Insumo ($):</label>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-secondary" id="cobrar_costo_laboratorio" name="costo_laboratorio" value="0.00" oninput="recalcularConversionCobro()" title="Deducción de insumos de terceros antes de repartir">
                                </div>
                            </div>

                            <!-- Desglose automático del adicional -->
                            <div class="p-2 rounded-2 bg-white border small">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div>
                                        <span class="text-muted">Laboratorio:</span> <strong id="dsp_adic_lab">$0.00</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">Neto a Repartir:</span> <strong id="dsp_adic_neto" class="text-primary">$0.00</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">↳ Especialista (<span id="dsp_adic_pct_med">50%</span>):</span> <strong id="dsp_adic_med" class="text-success">$0.00</strong>
                                    </div>
                                    <div>
                                        <span class="text-muted">↳ Centro ADH:</span> <strong id="dsp_adic_adh" class="text-info">$0.00</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configuración de Tasa de Cobro -->
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-auto">
                            <label class="form-label mb-0 small fw-bold">Tasa BCV Aplicada:</label>
                        </div>
                        <div class="col-md-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">Bs/$</span>
                                <input type="number" step="0.01" class="form-control fw-bold" id="cobrar_tasa_bcv" name="tasa_bcv" onchange="recalcularConversionCobro()" required>
                            </div>
                        </div>
                        <div class="col text-end">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input" type="checkbox" id="checkPagoMixto" onchange="togglePagoMixto()">
                                <label class="form-check-label fw-bold small text-primary" for="checkPagoMixto">Habilitar Pago Mixto / Dividido</label>
                            </div>
                        </div>
                    </div>

                    <!-- FORMA DE PAGO 1 -->
                    <div class="p-3 mb-3 bg-white rounded-3 border">
                        <h6 class="fw-bold text-dark mb-2 d-flex justify-content-between">
                            <span><i class="bi bi-wallet2 text-success me-1"></i> Forma de Pago 1:</span>
                            <span class="badge bg-light text-muted" id="badge_total_f1">$0.00</span>
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small text-muted">Método:</label>
                                <select class="form-select form-select-sm" id="cobrar_forma_1" name="forma_1" required onchange="actualizarMonedaForma1()">
                                    <!-- Se llena dinámicamente -->
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Monto ($ USD):</label>
                                <input type="number" step="0.01" class="form-control form-select-sm fw-bold" id="cobrar_monto_1" name="monto_1" value="50.00" oninput="recalcularConversionCobro()" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Equivalente en Bs:</label>
                                <input type="text" class="form-control form-select-sm bg-light fw-bold text-end" id="cobrar_monto_bs_1" readonly>
                            </div>
                            <div class="col-md-12 mt-2">
                                <input type="text" class="form-control form-control-sm" id="cobrar_referencia_1" name="referencia_1" placeholder="Referencia / N° Lote / Teléfono (opcional)">
                            </div>
                        </div>
                    </div>

                    <!-- FORMA DE PAGO 2 (Opcional - Pago Mixto) -->
                    <div id="container_pago_2" class="p-3 mb-3 bg-white rounded-3 border d-none border-primary">
                        <h6 class="fw-bold text-dark mb-2 d-flex justify-content-between">
                            <span><i class="bi bi-wallet2 text-primary me-1"></i> Forma de Pago 2 (Complemento):</span>
                            <span class="badge bg-light text-muted" id="badge_total_f2">$0.00</span>
                        </h6>
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label small text-muted">Método:</label>
                                <select class="form-select form-select-sm" id="cobrar_forma_2" name="forma_2">
                                    <!-- Se llena dinámicamente -->
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Monto ($ USD):</label>
                                <input type="number" step="0.01" class="form-control form-select-sm fw-bold" id="cobrar_monto_2" name="monto_2" value="0.00" oninput="recalcularConversionCobro()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Equivalente en Bs:</label>
                                <input type="text" class="form-control form-select-sm bg-light fw-bold text-end" id="cobrar_monto_bs_2" readonly>
                            </div>
                            <div class="col-md-12 mt-2">
                                <input type="text" class="form-control form-control-sm" id="cobrar_referencia_2" name="referencia_2" placeholder="Referencia / N° Lote / Teléfono (opcional)">
                            </div>
                        </div>
                    </div>

                    <!-- Facturación y Estado Final (Oculto para simplificar la interfaz) -->
                    <div class="row g-3 p-3 bg-light rounded-3 border d-none">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                <i class="bi bi-receipt me-1"></i> N° Factura / Empleado Responsable:
                            </label>
                            <input type="text" class="form-control form-control-sm" id="cobrar_factura_o_empleado" name="factura_o_empleado" placeholder="Ej: FAC-00123 o Nombre del empleado">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">
                                <i class="bi bi-tag me-1"></i> Asentar Cita como:
                            </label>
                            <select class="form-select form-select-sm fw-bold" id="cobrar_estado_final" name="estado_final">
                                <option value="Pagado">💲 Pagado (Recibo provisional)</option>
                                <option value="Facturado" selected>📦 Facturado (Cerrado en libro contable)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">
                        <i class="bi bi-check-circle me-1"></i> Confirmar y Guardar Cobro
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
