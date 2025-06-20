<?php
require_once __DIR__ . '/../services/mercadoPago.php';
require_once __DIR__ . '/../services/facturaService.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../services/pagoService.php';

class PagoController {
    public static function procesarWebhook() {
        Logger::logWebhook("↪️ Webhook recibido");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Logger::logWebhook("❌ Método no permitido: {$_SERVER['REQUEST_METHOD']}");
            http_response_code(405);
            exit;
        }

        $raw = file_get_contents('php://input');
        Logger::logWebhook("📩 Body recibido: $raw");

        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Logger::logWebhook("❌ JSON inválido: " . json_last_error_msg());
            http_response_code(400);
            return;
        }

        if (($input['type'] ?? '') !== 'payment') {
            Logger::logWebhook("⚠️ Tipo ignorado: " . ($input['type'] ?? 'N/A'));
            http_response_code(200); // OK pero no lo procesamos
            return;
        }

        $paymentId = $input['data']['id'] ?? null;
        if (!$paymentId) {
            Logger::logWebhook("❌ No se encontró 'data.id'");
            http_response_code(400);
            return;
        }

        Logger::logWebhook("🔍 Consultando pago ID: $paymentId");
        $pago = MercadoPagoService::obtenerPagoPorId($paymentId);

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

        if (FacturaService::yaFueFacturado($pago->id)) {
            Logger::logWebhook("⚠️ Ya se procesó una factura para el pago ID {$pago->id}");
            http_response_code(200);
            return;
        }

        // Guarda el pago en BD
        try {
            PagoService::guardarPagoDesdeObjeto($pago);
            Logger::logWebhook("💾 Pago guardado en base de datos.");
        } catch (Exception $e) {
            Logger::logWebhook("❌ Error guardando el pago: " . $e->getMessage());
            http_response_code(500);
            return;
        }

        // Genera la factura
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
