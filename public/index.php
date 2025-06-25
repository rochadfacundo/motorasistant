<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key   = $_ENV['MP_PUBLIC_KEY'];

MercadoPagoConfig::setAccessToken($access_token);

$preference = null;
$link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? 'SinNombre';
    $apellido = $_POST['apellido'] ?? 'SinApellido';
    $email = $_POST['email'] ?? 'correo@invalido.com';
    $contrato = $_POST['contrato'] ?? '0000';
    $monto = floatval($_POST['monto']) ?: 1;

    $client = new PreferenceClient();

        $backUrls = [
            "success" => "https://9ca6-2802-8010-b1ed-1000-7dbc-9d78-8214-87c7.ngrok-free.app/redirects/success.php",
            "failure" => "https://9ca6-2802-8010-b1ed-1000-7dbc-9d78-8214-87c7.ngrok-free.app/redirects/failure.php",
            "pending" => "https://9ca6-2802-8010-b1ed-1000-7dbc-9d78-8214-87c7.ngrok-free.app/redirects/pending.php",
        ];


    try {
        $preference = $client->create([
            "items" => [[
                "id" => uniqid(),
                "title" => "Contrato $contrato",
                "description" => "Plan seleccionado: $contrato",
                "quantity" => 1,
                "unit_price" => $monto
            ]],
            "back_urls" => $backUrls,
            "auto_return" => "approved",
            "payment_methods" => ["installments" => 12],
            "payer" => [
                "name" => $nombre,
                "surname" => $apellido,
                "email" => $email,
            ],
            "statement_descriptor" => "Motor assistant",
            "external_reference" => "Contrato$contrato-" . uniqid()
        ]);
        $link = $preference->init_point;
    } catch (MPApiException $e) {
        echo "<h1>Error al crear la preferencia (API):</h1>";
        echo "<pre>" . print_r($e->getApiResponse(), true) . "</pre>";
    } catch (Exception $e) {
        echo "<h1>Error inesperado:</h1>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
}

$pageTitle = "Pago con Checkout Pro";
require_once __DIR__ . '/../head.php';
?>

<body class="d-flex flex-column min-vh-100">
<?php require_once __DIR__ . '/../header.php'; ?>

<main class="container my-5 flex-grow-1">
    <h2>Formulario de Compra</h2>

    <form method="POST" class="row g-3 mb-5">
        <div class="col-md-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" name="nombre" required>
        </div>
        <div class="col-md-6">
            <label for="apellido" class="form-label">Apellido</label>
            <input type="text" class="form-control" name="apellido" required>
        </div>
        <div class="col-md-6">
            <label for="email" class="form-label">Correo electrónico</label>
            <input type="email" class="form-control" name="email" required>
        </div>
        <div class="col-md-6">
            <label for="contrato" class="form-label">Contrato</label>
            <select class="form-select" name="contrato" required>
                <option value="0301">Contrato 0301</option>
                <option value="0302">Contrato 0302</option>
                <option value="0303">Contrato 0303</option>
                <option value="0304">Contrato 0304</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="monto" class="form-label">Monto a pagar</label>
            <input type="number" class="form-control" name="monto" required step="0.01" min="1">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Generar botón de pago</button>
        </div>
    </form>

    <?php if ($preference): ?>
        <h2 class="mt-4">Botón de pago con MercadoPago - Checkout Pro</h2>
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
        <p>🔗 Link de pago directo MP:
            <a href="<?= $link ?>" target="_blank"><?= $link ?></a>
        </p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>
