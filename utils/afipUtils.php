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
 *  prepararAutenticacionAfip()
 * ---------------------------------------------------------------
 * Comprueba si el TA.xml es válido. Si no lo es, ejecuta el script
 * `wsaa-client.php` para obtener un nuevo token de autenticación.
 * ================================================================
 */
function prepararAutenticacionAfip(): void {

    //Ruta ta
    $rutaTA = __DIR__ . '/../factura/certs/TA.xml';  
    //Ruta certs  
    $certsPath = realpath(__DIR__ . '/../factura/certs');

    // Comando para ejecutar el cliente WSAA (SOAP)
    $wsfe="php wsaa-client.php wsfe";

    // Si el token expiró, regenerar   
    if (token_expirado($rutaTA)) {
        $salida = [];
        $codigo = 0;

        // Cambia al directorio de los certificados y ejecuta
        $cmd = "cd {$certsPath} && {$wsfe}";

        //Llamada para obtener el ta.xml
        exec($cmd, $salida, $codigo);


        // Registro en log de resultados
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
 *  obtenerDatosFactura($monto, $tipoFactura, $docTipo, $docNro)
 * ---------------------------------------------------------------
 * Se conecta al WSFE de AFIP para emitir un comprobante electrónico.
 *
 * Pasos:
 *  1️ Verifica y renueva el token TA si es necesario.
 *  2️ Inicializa la instancia `Afip` con CUIT, key y cert.
 *  3️ Calcula los importes neto, IVA y total.
 *  4️ Envía la estructura al servicio WSFEv1 de AFIP.
 *  5️ Devuelve número, CAE, tipo y vencimiento.
 *
 * @param float  $monto        Importe total o neto (según tipo)
 * @param string $tipoFactura  Tipo (A, B o C)
 * @param int    $docTipo      Tipo de documento receptor (80 = CUIT, 99 = CF)
 * @param int    $docNro       Número de documento del receptor
 * @return array Datos del comprobante generado
 * ================================================================
 */
function obtenerDatosFactura(float $monto, string $tipoFactura = 'B', int $docTipo = 99, int $docNro = 0): array {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/logger.php';

    Logger::logWebhook("🛡 Preparando autenticación con AFIP...");
    prepararAutenticacionAfip();

    // CUIT emisor registrado ante AFIP
    $CUIT = "30718607961";


    $afip = new Afip([
        'CUIT' => $CUIT,
        'production' => false, // Cambiar a TRUE en entorno produccion
        'cert' => file_get_contents(__DIR__ . '/../factura/certs/cert.pem'),
        'key'  => file_get_contents(__DIR__ . '/../factura/certs/key.pem')
    ]);

    try {
        // Determinar tipo de comprobante (AFIP codes)
        switch (strtoupper($tipoFactura)) {
            case 'A': $cbteTipo = 1; break;
            case 'B': $cbteTipo = 6; break;
            case 'C': default: $cbteTipo = 11; break;
}
        
       
        $ptoVta = 1;      // Punto de venta configurado en AFIP
        $concepto = 2;    // Servicios

        // Obtener el último comprobante autorizado cae
        $lastVoucher = $afip->ElectronicBilling->GetLastVoucher($ptoVta, $cbteTipo);
        $nroComprobante = $lastVoucher + 1;

        // Calcular IVA y neto
        $incluyeIVA = in_array($cbteTipo, [1, 6]); // Factura A o B
        $ivaPorcentaje = 21;
        $importeIVA = $incluyeIVA ? round($monto * $ivaPorcentaje / 121, 2) : 0.00;
        $importeNeto = $incluyeIVA ? round($monto - $importeIVA, 2) : round($monto, 2);
        $montoRedondeado = round($importeNeto + $importeIVA, 2);

        // Condición IVA receptor
        $condicionIVAReceptor = ($cbteTipo === 1) ? 1 : 5;

        // Estructura de datos a enviar a AFIP
        $data = [
            'CbteTipo'      => $cbteTipo,
            'PtoVta'        => $ptoVta,
            'Concepto'      => $concepto,
            'DocTipo'       => $docTipo,
            'DocNro'        => $docNro,
            'CbteDesde'     => $nroComprobante,
            'CbteHasta'     => $nroComprobante,
            'CbteFch'       => date('Ymd'),
            'ImpTotal'      => $montoRedondeado,
            'ImpTotConc'    => 0.00,
            'ImpNeto'       => $importeNeto,
            'ImpOpEx'       => 0.00,
            'ImpIVA'        => $importeIVA,
            'ImpTrib'       => 0.00,
            'CondicionIVAReceptorId' => $condicionIVAReceptor,
            'FchServDesde'  => date('Ymd'),
            'FchServHasta'  => date('Ymd'),
            'FchVtoPago'    => date('Ymd'),
            'MonId'         => 'PES',
            'MonCotiz'      => 1.00
        ];

        // Si corresponde IVA, agregar detalle
        if ($incluyeIVA) {
            $data['Iva'] = [[
                'Id'       => 5, // 21%
                'BaseImp'  => $importeNeto,
                'Importe'  => $importeIVA
            ]];
        }

        Logger::logWebhook("🧾 JSON enviado a AFIP:\n" . json_encode($data, JSON_PRETTY_PRINT));
        Logger::logWebhook("🧮 Verificación suma: ImpTotal={$montoRedondeado}, suma=" . ($importeNeto + $importeIVA));
        

        // Enviar solicitud de emisión
        $res = $afip->ElectronicBilling->CreateVoucher($data);


        // Analizar respuesta
        $detalle = $res['FeDetResp']['FECAEDetResponse'][0] ?? $res;
        $cae = $detalle['CAE'] ?? null;
        $fechaVencimientoCae = $detalle['CAEFchVto'] ?? null;
        $ptoVtaResp = $res['FeCabResp']['PtoVta'] ?? $ptoVta;
        $cbteDesde = $res['FeCabResp']['CbteDesde'] ?? $nroComprobante;

        // Si AFIP no devuelve CAE, error
        if (empty($cae)) {
            Logger::logWebhook("❌ AFIP no devolvió un CAE válido. Respuesta completa:\n" . json_encode($res, JSON_PRETTY_PRINT));
            return [
                'numero'        => null,
                'nroFormateado' => null,
                'cae'           => 'ERROR',
                'tipo'          => 'Error',
                'ptoVta'        => 0
            ];
        }

        Logger::logWebhook("✅ Comprobante emitido. CAE: " . $cae);

        // Devolver datos formateados
        return [
            'numero'        => $cbteDesde,
            'nroFormateado' => str_pad($ptoVtaResp, 4, '0', STR_PAD_LEFT) . '-' . str_pad($cbteDesde, 8, '0', STR_PAD_LEFT),
            'cae'           => $cae,
            'fechaVencimientoCae' => $fechaVencimientoCae,
            'codigoTipo'    => $cbteTipo,
            'tipo'          => tipoFacturaPorCodigo($cbteTipo),
            'ptoVta'        => $ptoVtaResp
        ];

        } catch (\Throwable $th) {
        // Captura detallada del error (mensaje + línea + stackTrace)
        Logger::logWebhook("❌ Error al emitir factura:\n" .
            "🧨 Mensaje: " . $th->getMessage() . "\n" .
            "📂 Archivo: " . $th->getFile() . "\n" .
            "📍 Línea: " . $th->getLine() . "\n" .
            "📋 Trace: " . $th->getTraceAsString()
        );

        return [
            'numero'        => null,
            'nroFormateado' => null,
            'cae'           => 'ERROR',
            'tipo'          => 'Error',
            'ptoVta'        => 0
        ];
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


