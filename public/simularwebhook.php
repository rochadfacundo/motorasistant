<?php
require_once __DIR__ . '/../controller/pagoController.php';
require_once __DIR__ . '/../services/mercadoPago.php';
require_once __DIR__ . '/../services/facturaService.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// ID del pago aprobado real
$paymentId = '116504937181';


$pago = MercadoPagoService::obtenerPagoPorId($paymentId);

if ($pago) {
    echo "📦 Pago encontrado. Estado: {$pago->status}\n";

    if ($pago->status === 'approved') {
        if (FacturaService::yaFueFacturado($pago->id)) {
            echo "⚠️ Ya existe una factura para el pago {$pago->id}\n";
        } else {
            echo "🧾 Generando factura...\n";
            FacturaService::generarYGuardarFactura($pago);
        }
    } else {
        echo "⚠️ El pago no está aprobado. Estado actual: {$pago->status}\n";
    }
} else {
    echo "❌ No se encontró el pago con ID: $paymentId\n";
}
