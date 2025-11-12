<?php
/**
 * ================================================================
 * MotorAssistant - Controlador de Webhook de Pagos (Mercado Pago)
 * ---------------------------------------------------------------
 * Este controlador recibe las notificaciones (webhooks) enviadas
 * por Mercado Pago cuando cambia el estado de un pago.
 * 
 * Flujo general:
 * 1 Mercado Pago envía un POST a este endpoint.
 * 2️ Se valida la estructura y tipo de evento recibido.
 * 3️ Se consulta la API de Mercado Pago para obtener los detalles del pago.
 * 4️ Si el pago está aprobado → se guarda en la base de datos.
 * 5️ Se genera la factura electrónica (AFIP).
 * ================================================================
 */


// Servicios principales del sistema
require_once __DIR__ . '/../services/mercadoPago.php';   // Interfaz con la API de Mercado Pago
require_once __DIR__ . '/../services/facturaService.php'; // Generación de facturas AFIP
require_once __DIR__ . '/../utils/logger.php';            // Registro detallado de logs
require_once __DIR__ . '/../services/pagoService.php';    // Persistencia de pagos en BD

class PagoController {

/**
 * Procesa el webhook entrante desde Mercado Pago.
 * 
 * Se encarga de validar, registrar y responder al evento de notificación. 
 * Solo se procesan eventos del tipo "payment".
 */
    public static function procesarWebhook() {

        // Log inicial - llegada del webhook
        Logger::logWebhook("↪️ Webhook recibido");

         // Valida método HTTP (solo POST)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::logWebhook("❌ Método no permitido: {$_SERVER['REQUEST_METHOD']}");
            http_response_code(405);
            exit;
        }

        // Lee el cuerpo crudo del POST (JSON)
        $raw = file_get_contents('php://input');
        Logger::logWebhook("📩 Body recibido: $raw");

        // Decodifica JSON
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::logWebhook("❌ JSON inválido: " . json_last_error_msg());
            http_response_code(400);
            return;
        }

        // Valida tipo de evento (solo procesa "payment")
        if (($input['type'] ?? '') !== 'payment') {
            Logger::logWebhook("⚠️ Tipo ignorado: " . ($input['type'] ?? 'N/A'));
            http_response_code(200); // Responde OK pero no se procesa
            return;
        }

         // Extrae ID de pago desde la notificación
        $paymentId = $input['data']['id'] ?? null;
        if (!$paymentId) {
            Logger::logWebhook("❌ No se encontró 'data.id'");
            http_response_code(400);
            return;
        }

        Logger::logWebhook("🔍 Consultando pago ID: $paymentId");

        // Consulta a la API de Mercado Pago para obtener detalles del pago
        $pago = MercadoPagoService::obtenerPagoPorId($paymentId);

        Logger::logWebhook("🧾 External Reference: {$pago->external_reference}");

        // Solo procesa pagos aprobados
        if (!$pago) {
            Logger::logWebhook("❌ No se pudo obtener el pago desde MP.");
            http_response_code(404);
            return;
        }

        if ($pago->status !== 'approved') {
            Logger::logWebhook("⚠️ Pago no aprobado. Estado: {$pago->status}");
            http_response_code(200);
            return;
        }

        // Evitar duplicar facturación si ya fue procesado
        if (FacturaService::yaFueFacturado($pago->id)) {
            Logger::logWebhook("⚠️ Ya se procesó una factura para el pago ID {$pago->id}");
            http_response_code(200);
            return;
        }

        /**
         * ================================================================
         *  PASO 1: GUARDAR EL PAGO EN BASE DE DATOS
         * ---------------------------------------------------------------
         * Se almacenan los datos del pago aprobado para mantener registro
         * de las transacciones.
         * ================================================================
         */
        try {
            PagoService::guardarPagoDesdeObjeto($pago);
            Logger::logWebhook("💾 Pago guardado en base de datos.");
        } catch (Exception $e) {
            Logger::logWebhook("❌ Error guardando el pago: " . $e->getMessage());
            http_response_code(500);
            return;
        }

       /**
         * ================================================================
         * PASO 2: GENERAR FACTURA ELECTRÓNICA
         * ---------------------------------------------------------------
         * Utiliza los datos del pago para emitir una factura con AFIP.
         * Si la facturación falla, se deja constancia en el log.
         * ================================================================
         */
        try {
            FacturaService::generarYGuardarFactura($pago);
            Logger::logWebhook("✅ Factura generada con éxito.");
        } catch (Exception $e) {
            Logger::logWebhook("❌ Error al generar factura: " . $e->getMessage());
            http_response_code(500);
            return;
        }

        http_response_code(200);
    }
}
