<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__, '.env.mp');
$dotenv->load();

$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key   = $_ENV['MP_PUBLIC_KEY'];

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
MercadoPagoConfig::setAccessToken($access_token);

$client = new PreferenceClient();

$backUrls=[
  "success"=>"https://c4cf-2802-8010-b108-2900-f03-cd82-4992-c33.ngrok-free.app/motorasistant/redirects/success.php",
  "failure"=>"https://c4cf-2802-8010-b108-2900-f03-cd82-4992-c33.ngrok-free.app/motorasistant/index.php",
  "pending"=>"https://c4cf-2802-8010-b108-2900-f03-cd82-4992-c33.ngrok-free.app/motorasistant/redirects/pending.php",
];
try {
$preference = $client->create([
  "items"=>[
    [
      "id"=>"15125123123",
      "title"=>"Contrato 0303 Factura Nro 456",
      "description"=>"Plan Potenciado",
      "quantity"=>1,
      "unit_price"=>5
    ],
  ],

  "back_urls"=> $backUrls,
  "auto_return"=>"approved",
  "payment_methods"=>[
    "installments"=>12
  ],
  "statement_descriptor"=>"Motor assistant",
  "external_reference"=>"PlanPotenciadoMA91203"
]);
$link = $preference->init_point;  
} catch (Exception $e) {
  echo "<h1>Error al crear la preferencia:</h1>";

  // Si es una excepción de MercadoPago, podés acceder al response
  if (method_exists($e, 'getApiResponse')) {
      echo "<pre>";
      print_r($e->getApiResponse());
      echo "</pre>";
  } else {
      echo "<pre>" . $e->getMessage() . "</pre>";
  }
}

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
        redirectMode: 'self'
      }
    });
  </script>

<p>
  🔗 Link de pago directo: 
  <a href="<?= $preference->init_point ?>" target="_blank">
    <?= $preference->init_point ?>
  </a>
</p>
</body>
</html>
