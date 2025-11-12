<?php
/**
 * ===================================================================
 * MotorAssistance - Cliente WSFE para AFIP (sin SDK, solo SoapClient)
 * -------------------------------------------------------------------
 * Este script usa el TA.xml ya generado por wsaa-client.php para
 * realizar operaciones contra el WebService de Facturación Electrónica.
 * ===================================================================
 */

date_default_timezone_set('America/Argentina/Buenos_Aires');

function emitirFacturaAFIP(float $importe, string $tipoFactura = 'B', int $docTipo = 99, int $docNro = 0): array {
    // CUIT emisor (el mismo que usaste al pedir el TA)
    $CUIT = '30718607961'; // ⚠️ Asegurate que coincida con el CUIT del certificado + TA

    $taPath = __DIR__ . '/TA.xml';
    $wsfeUrl = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';
    $wsfeWsdl = $wsfeUrl . '?WSDL';

    if (!file_exists($taPath)) {
        return ['cae' => 'ERROR', 'mensaje' => "❌ No se encontró el TA.xml en: $taPath"];
    }

    $ta = simplexml_load_file($taPath);
    if (!$ta) {
        return ['cae' => 'ERROR', 'mensaje' => '❌ Error al parsear el TA.xml'];
    }

    $token = (string)$ta->credentials->token;
    $sign = (string)$ta->credentials->sign;

    $client = new SoapClient($wsfeWsdl, [
        'soap_version' => SOAP_1_2,
        'location' => $wsfeUrl,
        'trace' => 1,
        'exceptions' => 1
    ]);

    switch (strtoupper($tipoFactura)) {
        case 'A': 
            $cbteTipo = 1; 
            break;
        case 'B': 
            $cbteTipo = 6; 
            break;
        case 'C': $cbteTipo = 11; 
            break;
    }

    try {
        // ==================== Paso 1: Último comprobante ====================
        $paramsUltimo = [
            'Auth' => [
                'Token' => $token,
                'Sign' => $sign,
                'Cuit' => $CUIT
            ],
            'PtoVta' => 1,
            'CbteTipo' => $cbteTipo
        ];

        $response = $client->FECompUltimoAutorizado($paramsUltimo);
        $ultimo = $response->FECompUltimoAutorizadoResult->CbteNro;
        $nuevoCbte = $ultimo + 1;

        // ==================== Paso 2: Solicitar CAE ====================
        $paramsCAE = [
            'Auth' => [
                'Token' => $token,
                'Sign' => $sign,
                'Cuit' => $CUIT
            ],
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => 1,
                    'CbteTipo' => $cbteTipo
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [[
                        'Concepto' => 2, // 2 = Servicios
                        'DocTipo' => $docTipo,
                        'DocNro' => $docNro,
                        'CbteDesde' => $nuevoCbte,
                        'CbteHasta' => $nuevoCbte,
                        'CbteFch' => date('Ymd'),
                        'FchServDesde' => date('Ymd'),
                        'FchServHasta' => date('Ymd'),
                        'FchVtoPago' => date('Ymd'),
                        'ImpTotal' => $importe,
                        'ImpNeto' => $importe,
                        'ImpIVA' => 0.00,
                        'ImpOpEx' => 0.00,
                        'ImpTotConc' => 0.00,
                        'ImpTrib' => 0.00,
                        'MonId' => 'PES',
                        'MonCotiz' => 1,
                        'CondicionIVAReceptorId' => 5,
                        'Iva' => [
                                'AlicIva' => [[
                                    'Id' => 3,            // 3 = Exento
                                    'BaseImp' => $importe,
                                    'Importe' => 0.00
                                ]]
                            ]
                    ]]
                ]
            ]
        ];

        $res = $client->FECAESolicitar($paramsCAE);
        $resp = $res->FECAESolicitarResult->FeDetResp->FECAEDetResponse ?? null;


        file_put_contents(__DIR__ . '/../../logs/wsfe-debug.log', print_r($res, true));

        if (!$resp || empty($resp->CAE)) {
            return ['cae' => 'ERROR', 'mensaje' => 'AFIP no devolvió CAE'];
        }

        return [
            'numero' => $nuevoCbte,
            'nroFormateado' => str_pad(1, 4, '0', STR_PAD_LEFT) . '-' . str_pad($nuevoCbte, 8, '0', STR_PAD_LEFT),
            'cae' => $resp->CAE,
            'fechaVencimientoCae' => $resp->CAEFchVto,
            'codigoTipo' => $cbteTipo,
            'tipo' => match ($cbteTipo) {
                1 => 'A',
                6 => 'B',
                11 => 'C',
                default => 'Desconocido'
            },
            'ptoVta' => 1
        ];

    } catch (SoapFault $e) {
        return [
            'cae' => 'ERROR',
            'mensaje' => "🧨 {$e->getMessage()}"
        ];
    }
}
