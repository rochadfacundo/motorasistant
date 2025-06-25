<?php



function token_expirado($rutaTA): bool {
    if (!file_exists($rutaTA)) return true;

    $xml = simplexml_load_file($rutaTA);
    $expirationTime = (string) $xml->header->expirationTime;

    return strtotime($expirationTime) <= time(); // Vencido o por vencer
}

function prepararAutenticacionAfip(): void {

    //Ruta ta
    $rutaTA = __DIR__ . '/../factura/certs/TA.xml';  
    //Ruta certs  
    $certsPath = realpath(__DIR__ . '/../factura/certs');

    //Llamada wsfe
    $wsfe="php wsaa-client.php wsfe";

    if (token_expirado($rutaTA)) {
        $salida = [];
        $codigo = 0;

        $cmd = "cd {$certsPath} && {$wsfe}";

        //Llamada para obtener el ta.xml
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

function obtenerDatosFactura(float $monto, int $docTipo = 99, int $docNro = 0): array {
    require_once __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/logger.php';

    $CUIT = "30718607961";
    $afip = new Afip([
        'CUIT' => $CUIT,
        'production' => false,
        'cert' => file_get_contents(__DIR__ . '/../factura/certs/cert.pem'),
        'key'  => file_get_contents(__DIR__ . '/../factura/certs/key.pem')
    ]);

    try {
        $cbteTipo = 11;
        $ptoVta = 1;
        $concepto = 2;

        $lastVoucher = $afip->ElectronicBilling->GetLastVoucher($ptoVta, $cbteTipo);
        $nroComprobante = $lastVoucher + 1;

        $data = [
            'CbteTipo'      => $cbteTipo,
            'PtoVta'        => $ptoVta,
            'Concepto'      => $concepto,
            'DocTipo'       => $docTipo,
            'DocNro'        => $docNro,
            'CbteDesde'     => $nroComprobante,
            'CbteHasta'     => $nroComprobante,
            'CbteFch'       => date('Ymd'),
            'ImpTotal'      => $monto,
            'ImpTotConc'    => 0.00,
            'ImpNeto'       => $monto,
            'ImpOpEx'       => 0.00,
            'ImpIVA'        => 0.00,
            'ImpTrib'       => 0.00,
            'CondicionIVAReceptorId' => 5,
            'FchServDesde'  => date('Ymd'),
            'FchServHasta'  => date('Ymd'),
            'FchVtoPago'    => date('Ymd'),
            'MonId'         => 'PES',
            'MonCotiz'      => 1.00
        ];

        Logger::logWebhook("🧾 JSON enviado a AFIP:\n" . json_encode($data, JSON_PRETTY_PRINT));
        $res = $afip->ElectronicBilling->CreateVoucher($data);

        // Intentar detectar estructura
        $detalle = $res['FeDetResp']['FECAEDetResponse'][0] ?? $res;
        $cae = $detalle['CAE'] ?? null;
        $ptoVtaResp = $res['FeCabResp']['PtoVta'] ?? $ptoVta;
        $cbteDesde = $res['FeCabResp']['CbteDesde'] ?? $nroComprobante;

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

        return [
            'numero'        => $cbteDesde,
            'nroFormateado' => str_pad($ptoVtaResp, 4, '0', STR_PAD_LEFT) . '-' . str_pad($cbteDesde, 8, '0', STR_PAD_LEFT),
            'cae'           => $cae,
            'tipo'          => tipoFacturaPorCodigo($cbteTipo),
            'ptoVta'        => $ptoVtaResp
        ];

    } catch (\Throwable $th) {
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






function tipoFacturaPorCodigo($codigo): string {
    return match ($codigo) {
        1 => 'A',
        6 => 'B',
        11 => 'C',
        default => 'Desconocido',
    };
}


