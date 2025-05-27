<?php
echo "<p>Link de pago: <a href='$link' target='_blank'>$link</a></p>";
require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key   = $_ENV['MP_PUBLIC_KEY'];

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
MercadoPagoConfig::setAccessToken($access_token);

$client = new PreferenceClient();

$link = $preference->init_point;  

$preference = $client->create([
  "items"=>[
    [
      "id"=>"15125123123",
      "title"=>"Contrato 0303 Factura Nro 456",
      "quantity"=>1,
      "unit_price"=>5
    ],
  ],
  "statement_descriptor"=>"Motor assistant",
  "external_reference"=>"PlanPotenciadoMA91203"
]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Pago con Checkout Pro</title>
  <script src="https://sdk.mercadopago.com/js/v2"></script>

  <style>
  #wallet_wrapper {
    max-width: 400px; /* Podés ajustar el ancho a gusto */
    margin: 0 auto;   /* Centra horizontalmente */
  }

  #wallet_container {
    width: 100%; /* Hace que el botón se ajuste al wrapper */
  }
</style>
</head>
<body>

  <h2>Botón de pago con MercadoPago - Checkout Pro</h2>

  <div id="wallet_wrapper">
  <div id="wallet_container"></div>
  </div>

  <script>
    const mp = new MercadoPago("<?= $public_key ?>");

    mp.bricks().create("wallet","wallet_container",{
      initialization:{
        preferenceId: '<?php echo $preference->id;?>',
        redirectMode: 'modal'
      }
    });
  </script>
</body>
</html>
