<?php

function token_expirado($rutaTA): bool {
    if (!file_exists($rutaTA)) return true;

    $xml = simplexml_load_file($rutaTA);
    $expirationTime = (string) $xml->header->expirationTime;

    return strtotime($expirationTime) <= time(); // Vencido o por vencer
}

function prepararAutenticacionAfip(): void {
    $rutaTA = __DIR__ . '/../factura/certs/TA.xml';

    if (token_expirado($rutaTA)) {
        $salida = [];
        $codigo = 0;

        
        $certsPath = realpath(__DIR__ . '/../factura/certs');
        $cmd = "cd {$certsPath} && php wsaa-client.php wsfe";
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

    $CUIT = 20356083882;
    
    $afip = new Afip([
        'CUIT' => $CUIT,
        'production' => false,
        'cert' => file_get_contents(__DIR__ . '/../factura/certs/cert.pem'),
        'key'  => file_get_contents(__DIR__ . '/../factura/certs/key.pem')

    ]);

    try {
        $cbteTipo = 11;  // Factura C
        $ptoVta = 1;

        $lastVoucher = $afip->ElectronicBilling->GetLastVoucher($CUIT, $cbteTipo, $ptoVta);
        $nroComprobante = $lastVoucher + 1;

        $data = [
            'CantReg'   => 1,
            'PtoVta'    => $ptoVta,
            'CbteTipo'  => $cbteTipo,
            'Concepto'  => 1,           // Productos
            'DocTipo'   => $docTipo,    // 99 = consumidor final, 80 = CUIT, etc.
            'DocNro'    => $docNro,
            'CbteDesde' => $nroComprobante,
            'CbteHasta' => $nroComprobante,
            'CbteFch'   => date('Ymd'),
            'ImpTotal'  => $monto,
            'ImpNeto'   => $monto,
            'ImpIVA'    => 0.00,
            'MonId'     => 'PES',
            'MonCotiz'  => 1
        ];

        Logger::logWebhook("🧾 Solicitando comprobante a AFIP: " . json_encode($data));

        $res = $afip->ElectronicBilling->CreateNextVoucher($data);

        Logger::logWebhook("✅ Comprobante emitido. CAE: " . $res['FeDetResp']['FECAEDetResponse'][0]['CAE']);

        return [
            'numero'       => $res['FeCabResp']['CbteDesde'], // ej: 12 (INT)
            'nroFormateado'=> str_pad($res['FeCabResp']['PtoVta'], 4, '0', STR_PAD_LEFT) . '-' .
                            str_pad($res['FeCabResp']['CbteDesde'], 8, '0', STR_PAD_LEFT),
            'cae'          => $res['FeDetResp']['FECAEDetResponse'][0]['CAE'],
            'tipo'         => tipoFacturaPorCodigo($res['FeCabResp']['CbteTipo']),
            'ptoVta'       => $res['FeCabResp']['PtoVta']
        ];
        
    } catch (\Throwable $th) {
        Logger::logWebhook("❌ Error al emitir factura: " . $th->getMessage());
        echo "❌ Error al emitir factura: " . $th->getMessage();
        return [
            'numero' => '0000-00000000',
            'cae'    => 'ERROR',
            'tipo'   => 'Error',
            'ptoVta' => 0
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

