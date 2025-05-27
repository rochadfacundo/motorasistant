<?php
require 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    file_put_contents('webhook.log', date('c') . " - Entrada: " . json_encode($input) . "\n", FILE_APPEND);

    if (isset($input['type']) && $input['type'] === 'payment') {
        $paymentId = $input['data']['id'];

        try {
            $pago = obtenerPagoPorId($paymentId);

            // Guardamos info del pago (esto podrías reemplazar por lógica de tu sistema)
            file_put_contents('webhook.log', "Pago recibido - Estado: {$pago->status}, Monto: {$pago->transaction_amount}\n", FILE_APPEND);
        } catch (Exception $e) {
            file_put_contents('webhook.log', "Error obteniendo pago: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

http_response_code(200); // Confirmar recepción al servidor de MercadoPago
