<?php
/**
 * ================================================================
 * MotorAssistant - Utilidades de Facturación AFIP
 * ---------------------------------------------------------------
 * Este módulo centraliza las funciones necesarias para:
 *  1️ Autenticarse ante AFIP (WSAA) y generar el Token (TA.xml)
 *  2️ Emitir comprobantes electrónicos (Factura A/B/C)
 *  3️ Calcular montos netos, IVA y total
 *  4️ Devolver datos relevantes: CAE, tipo, número y vencimiento
 * ================================================================
 */

/**
 * ================================================================
 * token_expirado($rutaTA)
 * ---------------------------------------------------------------
 * Verifica si el archivo TA.xml (token de autenticación) ha vencido.
 * AFIP exige renovar este token cada 12 horas.
 *
 * @param string $rutaTA Ruta completa del archivo TA.xml
 * @return bool true si el token está vencido o ausente
 * ================================================================
 */
function token_expirado($rutaTA): bool {
    if (!file_exists($rutaTA)) return true;

    $xml = simplexml_load_file($rutaTA);
    $expirationTime = (string) $xml->header->expirationTime;

    // Si la fecha de expiración ya pasó, se renueva
    return strtotime($expirationTime) <= time(); 
}

/**
 * ================================================================
 * prepararAutenticacionAfip()
 * ---------------------------------------------------------------
 * Comprueba si el TA.xml es válido. Si no lo es, ejecuta el script
 * `wsaa-client.php` para obtener un nuevo token de autenticación.
 * ================================================================
 */
function prepararAutenticacionAfip(): void {
    $certsPath = realpath(__DIR__ . '/../factura');
    $rutaTA = "$certsPath/TA.xml";

    // Comando para ejecutar el cliente WSAA (SOAP)
    $wsfe = "php wsaa-client.php wsfe";

    // Si el token expiró, regenerar   
    if (token_expirado($rutaTA)) {
        $salida = [];
        $codigo = 0;
        $cmd = "cd {$certsPath} && {$wsfe}";
        exec($cmd, $salida, $codigo);

        $logPath = __DIR__ . '/../logs/webhook.log';

        if ($codigo !== 0) {
            file_put_contents(
                $logPath,
                "[ERROR] wsaa-client.php falló con código $codigo\n" . implode("\n", $salida) . "\n",
                FILE_APPEND
            );
        } else {
            file_put_contents(
                $logPath,
                "[INFO] TA.xml generado correctamente:\n" . implode("\n", $salida) . "\n",
                FILE_APPEND
            );
        }
    }
}

/**
 * ================================================================
 * obtenerDatosFactura($monto, $tipoFactura, $docTipo, $docNro)
 * ---------------------------------------------------------------
 * Se conecta al WSFE de AFIP para emitir un comprobante electrónico.
 *
 * Pasos:
 *  1️ Verifica y renueva el token TA si es necesario.
 *  2️ Invoca el script wsfe-client.php (con SoapClient directo)
 *  3️ Devuelve número, CAE, tipo y vencimiento.
 *
 * @param float  $monto        Importe total o neto (según tipo)
 * @param string $tipoFactura  Tipo (A, B o C)
 * @param int    $docTipo      Tipo de documento receptor (80 = CUIT, 99 = CF)
 * @param int    $docNro       Número de documento del receptor
 * @return array Datos del comprobante generado
 * ================================================================
 */
function obtenerDatosFactura(float $monto, string $tipoFactura = 'B', int $docTipo = 99, int $docNro = 0): array {
    require_once __DIR__ . '/logger.php';

    Logger::logWebhook("🛡 Preparando autenticación con AFIP...");
    prepararAutenticacionAfip();

    try {
        require_once __DIR__ . '/../factura/certs/wsaa-client.php';



        $resultado = emitirFacturaAFIP($monto, $tipoFactura, $docTipo, $docNro);

        if ($resultado['cae'] === 'ERROR') {
            Logger::logWebhook("❌ Error devuelto por el WSFE manual: " . ($resultado['mensaje'] ?? 'Desconocido'));
        }

        return $resultado;

    } catch (\Throwable $th) {
        Logger::logWebhook("❌ Excepción al emitir factura con cliente manual:\n" .
            "🧨 Mensaje: " . $th->getMessage() . "\n" .
            "📂 Archivo: " . $th->getFile() . "\n" .
            "📍 Línea: " . $th->getLine() . "\n" .
            "📋 Trace: " . $th->getTraceAsString()
        );

        return ['numero' => null, 'nroFormateado' => null, 'cae' => 'ERROR', 'tipo' => 'Error', 'ptoVta' => 0];
    }
}

/**
 * ================================================================
 * tipoFacturaPorCodigo($codigo)
 * ---------------------------------------------------------------
 * Traduce los códigos numéricos de AFIP al tipo de factura legible.
 * ================================================================
 */
function tipoFacturaPorCodigo($codigo): string {
    return match ($codigo) {
        1 => 'A',
        6 => 'B',
        11 => 'C',
        default => 'Desconocido',
    };
}
