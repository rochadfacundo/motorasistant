<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;

$dotenv = Dotenv::createImmutable(__DIR__, '.env.mp');
$dotenv->load();

$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key   = $_ENV['MP_PUBLIC_KEY'];

MercadoPagoConfig::setAccessToken($access_token);

$client = new PreferenceClient();

$backUrls = [
    "success" => "https://4f2e-2802-8010-b199-b800-4b37-a19a-1a0-bb5.ngrok-free.app/motorasistant/redirects/success.php",
    "failure" => "https://4f2e-2802-8010-b199-b800-4b37-a19a-1a0-bb5.ngrok-free.app/motorasistant/redirects/failure.php",
    "pending" => "https://4f2e-2802-8010-b199-b800-4b37-a19a-1a0-bb5.ngrok-free.app/motorasistant/redirects/pending.php",
];

try {
    $preference = $client->create([
        "items" => [[
            "id" => "15125123123",
            "title" => "Contrato 0303 Factura Nro 456",
            "description" => "Plan Potenciado",
            "quantity" => 1,
            "unit_price" => 5
        ]],
        "back_urls" => $backUrls,
        "auto_return" => "approved",
        "payment_methods" => ["installments" => 12],
        "payer" => [
            "name" => "Juancito",
            "surname" => "Lopez",
            "email" => "comprador@gmail.com",
        ],
        "statement_descriptor" => "Motor assistant",
        "external_reference" => "PlanPotenciadoMA91203"
    ]);
    $link = $preference->init_point;
} catch (Exception $e) {
    echo "<h1>Error al crear la preferencia:</h1>";
    echo "<pre>" . (method_exists($e, 'getApiResponse') ? print_r($e->getApiResponse(), true) : $e->getMessage()) . "</pre>";
}

$pageTitle = "Pago con Checkout Pro";
require 'head.php';
?>

<body class="d-flex flex-column min-vh-100">
<?php require 'header.php'; ?>

<main class="container my-5 flex-grow-1">
    <h2>Botón de pago con MercadoPago - Checkout Pro</h2>

    <div id="wallet_wrapper" class="my-4">
        <div id="wallet_container"></div>
    </div>

    <script>
        const mp = new MercadoPago("<?= $public_key ?>");

        mp.bricks().create("wallet", "wallet_container", {
            initialization: {
                preferenceId: '<?= $preference->id ?>',
                redirectMode: 'self'
            }
        });
    </script>

    <p>
        🔗 Link de pago directo MP:
        <a href="<?= $link ?>" target="_blank"><?= $link ?></a>
    </p>
</main>

<?php require 'footer.php'; ?>
</body>
</html>
