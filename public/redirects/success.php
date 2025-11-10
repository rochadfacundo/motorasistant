<?php
/**
 * ================================================================
 * MotorAssistant - Página de Éxito (Pago aprobado)
 * ---------------------------------------------------------------
 * Este script se ejecuta cuando el usuario es redirigido desde
 * Mercado Pago con un pago aprobado (success URL).
 *
 * Flujo:
 * 1️ Recibe los parámetros GET de Mercado Pago (payment_id, etc.)
 * 2️ Inserta el registro de pago en la base de datos
 * 3️ Valida el estado del pago desde la API de MP
 * 4️ Genera la factura correspondiente (AFIP)
 * 5️ Muestra al usuario el mensaje de confirmación y enlace al PDF
 * ================================================================
 */

 // Dependencias principales
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/../../services/mercadoPago.php';
require_once __DIR__ . '/../../services/facturaService.php';
require_once __DIR__ . '/../../utils/db.php';
require_once __DIR__ . '/../../head.php';
require_once __DIR__ . '/../../header.php';

$pageTitle = "Pago aprobado";

// Configuración de errores (solo desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

//Variables de control de flujo
$data = $_GET;             // Parámetros devueltos por Mercado Pago
$pagoExitoso = false;      // Estado del proceso local
$rutaPdf = null;           // Ruta al PDF de la factura

try {
    // ================================================================
    // Conexión a la base de datos
    // ================================================================
    $pdo = DB::getConnection();

    /**
     * ================================================================
     * PASO 1: Registrar pago (SP insertarPago)
     * ---------------------------------------------------------------
     * Se insertan los datos recibidos en la tabla de pagos utilizando
     * un procedimiento almacenado en MySQL.
     * ================================================================
     */
    $stmt = $pdo->prepare("CALL insertarPago(
        :collection_id,
        :collection_status,
        :payment_id,
        :status,
        :external_reference,
        :payment_type,
        :merchant_order_id,
        :preference_id,
        :site_id,
        :processing_mode,
        :merchant_account_id
    )");

    // Asociar parámetros GET → SP
    $stmt->bindParam(':collection_id', $data['collection_id']);
    $stmt->bindParam(':collection_status', $data['collection_status']);
    $stmt->bindParam(':payment_id', $data['payment_id']);
    $stmt->bindParam(':status', $data['status']);
    $stmt->bindParam(':external_reference', $data['external_reference']);
    $stmt->bindParam(':payment_type', $data['payment_type']);
    $stmt->bindParam(':merchant_order_id', $data['merchant_order_id']);
    $stmt->bindParam(':preference_id', $data['preference_id']);
    $stmt->bindParam(':site_id', $data['site_id']);
    $stmt->bindParam(':processing_mode', $data['processing_mode']);
    $stmt->bindParam(':merchant_account_id', $data['merchant_account_id']);

    $stmt->execute();
    $pagoExitoso = true;

    /**
     * ================================================================
     * PASO 2: Validar pago en Mercado Pago
     * ---------------------------------------------------------------
     * Se consulta la API oficial para confirmar que el pago realmente
     * esté aprobado (estado 'approved').
     * ================================================================
     */
    $pago = MercadoPagoService::obtenerPagoPorId($data['payment_id']);

    if ($pago && $pago->status === 'approved') {

        // Tipo de factura por defecto (si no hay información previa)
        $tipoFactura = 'B';

         /**
         * ================================================================
         * PASO 3: Obtener tipo de factura según preferencia
         * ---------------------------------------------------------------
         * Si existe un SP que vincula la preferencia con el tipo de factura,
         * se ejecuta para determinar si corresponde Factura A, B o C.
         * ================================================================
         */
        try {
            $stmt = $pdo->prepare("CALL obtenerTipoFacturaPorPreferencia(:pPreferenceId)");
            $stmt->bindParam(':pPreferenceId', $data['preference_id']);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['tipo_factura'])) {
                $tipoFactura = $result['tipo_factura'];
            }
        } catch (PDOException $e) {
            Logger::logWebhook("⚠️ Error al obtener tipo de factura por SP: " . $e->getMessage());
        }

         /**
         * ================================================================
         * PASO 4: Generar y guardar factura
         * ---------------------------------------------------------------
         * Se llama al servicio de facturación, que internamente:
         *  - Obtiene o renueva el TA.xml de AFIP
         *  - Emite el comprobante con WSFEv1
         *  - Genera el PDF y lo guarda en /facturas/
         * ================================================================
         */
        FacturaService::generarYGuardarFactura($pago, $tipoFactura);

         /**
         * ================================================================
         * PASO 5: Obtener ruta del PDF generado
         * ---------------------------------------------------------------
         * Consulta la base de datos mediante un SP que retorna
         * la ubicación física del archivo de factura.
         * ================================================================
         */
        $stmt = $pdo->prepare("CALL obtenerRutaFacturaPorPaymentId(:pPaymentId)");
        $stmt->bindParam(':pPaymentId', $data['payment_id']);
        $stmt->execute();
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        $rutaPdf = $factura['ruta_pdf'] ?? null;
    }

} catch (PDOException $e) {
    $errorMensaje = $e->getMessage();
}
?>

<!-- ================================================================
     RENDERIZADO DEL RESULTADO AL USUARIO
     --------------------------------------------------------------- -->
<body class="d-flex flex-column min-vh-100">
<main class="container my-5 flex-grow-1">
    <div class="card shadow mx-auto text-center" style="max-width: 600px;">
        <div class="card-body p-5">
            <?php if ($pagoExitoso): ?>
                <div class="mb-4 text-success">
                    <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                </div>
                <h3 class="card-title text-success">¡Pago aprobado!</h3>
                <p class="card-text mt-3">
                    El pago se ha registrado correctamente.<br>
                    Nº de pago: <strong><?= htmlspecialchars($data['payment_id']) ?></strong><br>
                    Referencia: <strong><?= htmlspecialchars($data['external_reference']) ?></strong>
                </p>

                <div class="d-flex justify-content-center gap-2 mt-4">
                    <a href="/" class="btn btn-outline-success">← Volver</a>

                <?php if ($rutaPdf): ?>
                    <a href="/facturas/descargarFactura.php?archivo=<?= urlencode(basename($rutaPdf)) ?>" class="btn btn-outline-primary">
                        📄 Descargar factura
                    </a>
                <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mb-4 text-danger">
                    <i class="bi bi-x-circle-fill" style="font-size: 3rem;"></i>
                </div>
                <h3 class="card-title text-danger">Error al registrar el pago</h3>
                <p class="card-text"><?= htmlspecialchars($errorMensaje) ?></p>
                <a href="/" class="btn btn-outline-danger mt-4">← Volver</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../footer.php'; ?>
</body>
</html>
