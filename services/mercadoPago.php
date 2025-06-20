<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoService {
    public static function obtenerPagoPorId(string $paymentId) {
        MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);
        $client = new PaymentClient();
        return $client->get($paymentId);
    }
}
