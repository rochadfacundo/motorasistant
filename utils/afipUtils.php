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


function obtenerDatosFactura(): array {
 
    // ya se ejecuto FECAESolicitar y hay respuesta guardada
    global $afip, $CUIT;

    $lastVoucher = $afip->ElectronicBilling->GetLastVoucher($CUIT, 11, 1); 
    // tipo 11, pto vta 1
    $nroComprobante = $lastVoucher + 1;

    $data = [
        'CantReg' => 1,
        'PtoVta' => 1,
        'CbteTipo' => 11,
        'Concepto' => 1,
        'DocTipo' => 99,
        'DocNro' => 0,
        'CbteDesde' => $nroComprobante,
        'CbteHasta' => $nroComprobante,
        'CbteFch' => date('Ymd'),
        'ImpTotal' => 100.00,
        'ImpNeto' => 100.00,
        'ImpIVA' => 0.00,
        'MonId' => 'PES',
        'MonCotiz' => 1
    ];

    $res = $afip->ElectronicBilling->CreateNextVoucher($data);

    return [
        'numero' => str_pad($res['FeCabResp']['PtoVta'], 4, '0', STR_PAD_LEFT) . '-' .
                    str_pad($res['FeCabResp']['CbteDesde'], 8, '0', STR_PAD_LEFT),
        'cae' => $res['FeDetResp']['FECAEDetResponse'][0]['CAE'],
        'tipo' => tipoFacturaPorCodigo($res['FeCabResp']['CbteTipo']),
        'ptoVta' => $res['FeCabResp']['PtoVta']
    ];
}

function tipoFacturaPorCodigo($codigo): string {
    return match ($codigo) {
        1 => 'A',
        6 => 'B',
        11 => 'C',
        default => 'Desconocido',
    };
}

