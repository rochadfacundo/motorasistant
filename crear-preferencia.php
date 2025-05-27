<?php
require __DIR__ . '/vendor/autoload.php';
$config = require 'config.php';
// SDK de Mercado Pago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
MercadoPagoConfig::setAccessToken($config['access_token']);


$client = new PreferenceClient();

$preference = $client->create([
    "items"=> array(
      array(
        "title" => "Producto para Big babe",
        "quantity" => 1,
        "unit_price" => 10
      )
    )
  ]);

$preference->back_urls = [
    "success" => "https://6b47-2802-8010-b14c-dc00-255c-6d3b-f601-35c5.ngrok-free.app/redirects/success.php",
    "failure" => "https://6b47-2802-8010-b14c-dc00-255c-6d3b-f601-35c5.ngrok-free.app/redirects/failure.php",
    "pending" => "https://6b47-2802-8010-b14c-dc00-255c-6d3b-f601-35c5.ngrok-free.app/redirects/pending.php"
];
$preference->auto_return = "approved";

echo json_encode($preference);

header("Location: " . $preference->init_point);
exit;
