    <!-- Toast Notifications Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3 no-print" style="z-index: 1100;">
        <div id="liveToast" class="toast align-items-center text-white bg-primary border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fs-6" id="toastMessage">
                    Operación realizada exitosamente.
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS Local -->
    <script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
    
    <!-- Script Principal de la Aplicación -->
    <script src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../../assets/js/app.js') ?>"></script>
</body>
</html>
