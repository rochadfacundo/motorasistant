<?php
/**
 * ================================================================
 * MotorAssistant - Servicio de Integración con Mercado Pago
 * ---------------------------------------------------------------
 * Este servicio encapsula la lógica de conexión con el SDK oficial 
 * de Mercado Pago, permitiendo consultar información de pagos 
 * (Payment API).
 *
 * Se utiliza en:
 *  - webhook.php: para validar el pago antes de facturar
 *  - redirects/success.php: para mostrar datos del pago aprobado
 *
 * Flujo:
 * 1️ Carga las variables de entorno (.env)
 * 2️ Inicializa el SDK con el Access Token
 * 3️ Permite obtener detalles completos de un pago por ID
 * ================================================================
 */
require_once __DIR__ . '/../vendor/autoload.php';

// Cargar variables de entorno desde el archivo raíz
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Importar clases del SDK de Mercado Pago
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoService {

     /**
     * ================================================================
     * obtenerPagoPorId($paymentId)
     * ---------------------------------------------------------------
     * Consulta la API de Mercado Pago y devuelve un objeto con 
     * los datos completos del pago: monto, estado, mail del cliente, etc.
     *
     * @param string $paymentId ID del pago en Mercado Pago
     * @return object|null Objeto de pago (stdClass) o null si falla
     * ================================================================
     */
    public static function obtenerPagoPorId(string $paymentId) {

        try {
            // Configurar token de acceso para la sesión actual
            MercadoPagoConfig::setAccessToken($_ENV['MP_ACCESS_TOKEN']);

            // Instanciar cliente de pagos
            $client = new PaymentClient();

            // Obtener datos del pago por su ID
            return $client->get($paymentId);

        } catch (Exception $e) {
            // Manejo de errores o ID inválido
            error_log("Error al obtener pago Mercado Pago: " . $e->getMessage());
            return null;
        }
    }
}
