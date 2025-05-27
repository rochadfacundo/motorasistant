<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key = $_ENV['MP_PUBLIC_KEY'];

MercadoPagoConfig::setAccessToken($access_token);

function obtenerPagoPorId($paymentId): object {
    $paymentClient = new PaymentClient();
    return $paymentClient->get($paymentId);
}
