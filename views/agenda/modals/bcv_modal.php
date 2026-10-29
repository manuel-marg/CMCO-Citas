<!-- Modal Actualizar Tasa BCV -->
<div class="modal fade" id="modalBcv" tabindex="-1" aria-labelledby="modalBcvLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formBcv" onsubmit="guardarTasaBcv(event)">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title" id="modalBcvLabel">
                        <i class="bi bi-currency-exchange text-warning me-1"></i> Tasa Oficial Banco Central de Venezuela
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Esta tasa oficial es utilizada para calcular automáticamente el valor en Bolívares (Bs) al cobrar en Punto de Venta, Pago Móvil o Transferencia bancaria.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Tasa USD en Bolívares (Bs/$):</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Bs.</span>
                            <input type="number" step="0.0001" class="form-control form-control-lg fw-bold text-primary" id="bcv_input_tasa" name="tasa" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Fecha Valor Oficial:</label>
                        <input type="date" class="form-control" id="bcv_input_fecha_valor" name="fecha_valor" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold small text-muted">Origen de la Tasa:</label>
                        <input type="text" class="form-control form-control-sm" id="bcv_input_fuente" name="fuente" value="BCV Oficial" placeholder="BCV, Web, etc.">
                    </div>
                </div>
                
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-1"></i> Guardar Tasa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
