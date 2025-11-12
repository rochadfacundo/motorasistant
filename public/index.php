<?php
/**
 * ================================================================
 * MotorAssistant - Módulo de Pagos con Mercado Pago + Facturación
 * ---------------------------------------------------------------
 * Este script gestiona el flujo de creación de una preferencia de pago 
 * en Mercado Pago (Checkout Pro) y guarda los datos asociados en la bd
 * para luego continuar el proceso de facturación AFIP.
 * ================================================================
 */
require_once __DIR__ . '/../vendor/autoload.php';

// Configuración de errores (solo desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED);

// Imports SDK de Mercado Pago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

// Dependencias internas
require_once __DIR__ . '/../utils/db.php'; 
require_once __DIR__ . '/../services/preferenciaService.php';


// Carga de variables de entorno (.env)
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Credenciales de Mercado Pago obtenidas desde .env
$access_token = $_ENV['MP_ACCESS_TOKEN'];
$public_key   = $_ENV['MP_PUBLIC_KEY'];

// Inicialización del SDK con el Access Token
MercadoPagoConfig::setAccessToken($access_token);

// Variables para almacenar la preferencia creada
$preference = null;
$link = null;


// ===================================================================
// PROCESAMIENTO DEL FORMULARIO DE PAGO (POST)
// ===================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Datos del comprador
    $nombre = $_POST['nombre'] ?? 'SinNombre';
    $apellido = $_POST['apellido'] ?? 'SinApellido';
    $email = $_POST['email'] ?? 'correo@invalido.com';
    $contrato = $_POST['contrato'] ?? '0000';
    $tipoFactura = $_POST['tipo_factura'] ?? 'B';
    $monto = floatval($_POST['monto']) ?: 1;
    $cuit = $_POST['cuit'] ?? null;

    // Instancia del cliente de preferencias
    $client = new PreferenceClient();

    // URLs de retorno del flujo de pago
    $backUrls = [
        "success" => "https://e455ff4d5a1a.ngrok-free.app/motorasistant_mio/public/redirects/success.php",
        "failure" => "https://e455ff4d5a1a.ngrok-free.app/motorasistant_mio/public/redirects/failure.php",
        "pending" => "https://e455ff4d5a1a.ngrok-free.app/motorasistant_mio/public/redirects/pending.php",
    ];
    

    try {
    /**
     * PASO 1: CREAR PREFERENCIA EN MERCADO PAGO
     * ---------------------------------------------------------------
     * Se crea la preferencia incluyendo el campo external_reference
     * para poder identificarla luego desde el webhook. 
     * Esto elimina la necesidad de hacer una llamada "update" adicional.
     */
        $preference = $client->create([
            "external_reference" => "CONTRATO_" . $contrato . "_" . time(),
            "items" => [[
                "id" => uniqid(),  // ID interno único, puede ser del contrato
                "title" => "Contrato $contrato",  // Descripción del servicio
                "description" => "Plan seleccionado: $contrato",
                "quantity" => 1,
                "unit_price" => $monto
            ]],
            "back_urls" => $backUrls,
            "notification_url" => "https://e455ff4d5a1a.ngrok-free.app/motorasistant_mio/controller/pagoController.php",
            "auto_return" => "approved",  // Redirección automática tras pago aprobado
            "payment_methods" => ["installments" => 12], // Hasta 12 cuotas
            "payer" => [
                "name" => $nombre,
                "surname" => $apellido,
                "email" => $email,
            ],
            "statement_descriptor" => "Motor assistant" // Texto que aparece en el resumen de tarjeta
        ]);

        // Link de pago generado por Mercado Pago
        $link = $preference->init_point;

        /**
         * ================================================================
         *  PASO 3: GUARDAR EN BASE DE DATOS
         * ---------------------------------------------------------------
         * Guarda los datos principales de la preferencia creada, para 
         * luego asociarla con el pago y la factura emitida al confirmarse.
         * ================================================================
         */
        PreferenciaService::guardarPreferencia([
            'preference_id' => $preference->id,
            'tipo_factura' => $tipoFactura,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'contrato' => $contrato,
            'monto' => $monto,
            'cuit'  => $_POST['cuit'] ?? null
        ]);
        
    } catch (MPApiException $e) {
        // Error controlado de la API de Mercado Pago
        echo "<h1>Error al crear la preferencia (API):</h1>";
        echo "<pre>" . print_r($e->getApiResponse(), true) . "</pre>";
    } catch (Exception $e) {
        echo "<h1>Error inesperado:</h1>";
        echo "<pre>" . $e->getMessage() . "</pre>";
    }
}


// ===================================================================
// RENDERIZADO DE LA PÁGINA (Formulario + Botón de pago)
// ===================================================================
$pageTitle = "Pago con Checkout Pro";
require_once __DIR__ . '/../head.php';
?>

<body class="d-flex flex-column min-vh-100">
<?php require_once __DIR__ . '/../header.php'; ?>

<main class="container my-5 flex-grow-1">
    <h2>Formulario de Compra</h2>

    <!-- =========================================================
        FORMULARIO PRINCIPAL DE DATOS DEL COMPRADOR
    ========================================================= -->
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
    <label for="cuit" class="form-label">CUIT (solo factura A)</label>
    <input type="text" class="form-control" name="cuit" pattern="\d{11}">
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
            <label for="tipo_factura" class="form-label">Tipo de factura</label>
            <select class="form-select" name="tipo_factura" required>
                <option value="A">Factura A</option>
                <option value="B" selected>Factura B</option>
                <option value="C">Factura C</option>
            </select>
        </div>
        <div class="col-md-6">
            <label for="monto" class="form-label">Monto a pagar</label>
            <input type="number" class="form-control" name="monto" required step="0.01" min="1">
        </div>
        <div class="col-12 text-center">
            <button type="submit" class="btn btn-primary mx-auto">Ir a pagar</button>
        </div>
    </form>


     <!-- =========================================================
         BOTÓN DE PAGO MERCADO PAGO (Checkout Pro)
         Solo se muestra si la preferencia fue creada correctamente
         ========================================================= -->   
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

        <!-- Link alternativo (para enviar por mail?) -->
        <p>🔗 Link de pago directo MP:
            <a href="<?= $link ?>" target="_blank"><?= $link ?></a>
        </p>
    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/../footer.php'; ?>
</body>
</html>