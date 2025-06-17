<?php
require 'vendor/autoload.php';
require 'functions.php';
require_once 'factura/generarFactura.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

file_put_contents('webhook.log', date('c') . " - ENTRÓ CON REQUEST\n", FILE_APPEND);

use Dotenv\Dotenv;
use MercadoPago\MercadoPagoConfig;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

file_put_contents('webhook.log', "[1] .env cargado\n", FILE_APPEND);

MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
file_put_contents('webhook.log', "[2] Access token seteado\n", FILE_APPEND);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    file_put_contents('webhook.log', "[3] Método POST recibido\n", FILE_APPEND);

    $raw = file_get_contents('php://input');
    file_put_contents('webhook.log', "[4] Raw input: $raw\n", FILE_APPEND);

    $input = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents('webhook.log', "❌ Error al decodificar JSON: " . json_last_error_msg() . "\n", FILE_APPEND);
    }

    if (isset($input['type']) && $input['type'] === 'payment') {
        file_put_contents('webhook.log', "[5] Tipo = payment\n", FILE_APPEND);

        $paymentId = $input['data']['id'] ?? null;

        if (!$paymentId) {
            file_put_contents('webhook.log', "❌ No se encontró 'data.id' en el body\n", FILE_APPEND);
        } else {
            file_put_contents('webhook.log', "[6] payment_id: $paymentId\n", FILE_APPEND);

            try {
                $pago = obtenerPagoPorId($paymentId);
                file_put_contents('webhook.log', "[7] Pago obtenido. Estado: {$pago->status}, Monto: {$pago->transaction_amount}\n", FILE_APPEND);

                if ($pago->status === 'approved') {
                    file_put_contents('webhook.log', "[8] El pago está aprobado\n", FILE_APPEND);
                
                    $pagosLogPath = 'pagos.log';
                    $pagosLogContent = file_exists($pagosLogPath) ? file_get_contents($pagosLogPath) : '';
                
                    if (str_contains($pagosLogContent, (string)$pago->id)) {
                        file_put_contents('webhook.log', "⚠️ Ya procesado: payment_id {$pago->id}, se omite\n", FILE_APPEND);
                    } else {
                        $linea = date('c') . " - ✅ payment_id: {$pago->id} - Estado: {$pago->status} - Monto: {$pago->transaction_amount} - Email: {$pago->payer->email}\n";
                        file_put_contents($pagosLogPath, $linea, FILE_APPEND);
                
                        try {
                            $resultado = generarFactura([
                                "nombre" => $pago->payer->name,
                                "apellido" => $pago->payer->surname,
                                "email" => $pago->payer->email,
                                "monto" => $pago->transaction_amount,
                            ]);
                
                            file_put_contents('webhook.log', "✅ Factura generada con CAE: {$resultado['cae']}\n", FILE_APPEND);
                        } catch (Exception $e) {
                            file_put_contents('webhook.log', "❌ Error al generar factura: " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    }
                }

            } catch (Exception $e) {
                file_put_contents('webhook.log', "❌ Error obteniendo pago: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

    } else {
        file_put_contents('webhook.log', "❌ Tipo de notificación no es 'payment': " . json_encode($input) . "\n", FILE_APPEND);
    }
} else {
    file_put_contents('webhook.log', "❌ Método no permitido: {$_SERVER['REQUEST_METHOD']}\n", FILE_APPEND);
}

http_response_code(200);
