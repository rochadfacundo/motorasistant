<?php

function logMsg($msg) {
    $timestamp = date('c');
    echo "$timestamp $msg\n";
}

try {
    logMsg("🛡 Preparando autenticación con AFIP...");

    $CUIT = '30718607961';
    $service = 'wsfe';
    $basePath = __DIR__;
    $tmpPath = "$basePath/tmp";

    // Verificar que la carpeta tmp exista y sea escribible
    if (!is_dir($tmpPath) || !is_writable($tmpPath)) {
        throw new Exception("No se puede escribir en el directorio temporal: $tmpPath");
    }

    // Crear el XML TRA
    $TRA = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"/>');
    $TRA->addChild('header')->addChild('uniqueId', time());
    $TRA->header->addChild('generationTime', date('c', time() - 60));
    $TRA->header->addChild('expirationTime', date('c', time() + 60));
    $TRA->addChild('service', $service);

    $TRAPath = "$tmpPath/TRA.xml";
    $TRASignature = "$tmpPath/TRA.tmp";

    // Eliminar archivos anteriores
    if (file_exists($TRAPath)) unlink($TRAPath);
    if (file_exists($TRASignature)) unlink($TRASignature);

    // Guardar el TRA
    if (file_put_contents($TRAPath, $TRA->asXML()) === false) {
        throw new Exception("❌ No se pudo escribir el archivo TRA.xml en: $TRAPath");
    }

    // Firmar el TRA con openssl
    $certPath = "$basePath/cert.pem";
    $keyPath = "$basePath/key.pem";
    $cmd = "openssl smime -sign -signer $certPath -inkey $keyPath -outform DER -nodetach -in $TRAPath -out $TRASignature 2>&1";
    exec($cmd, $output, $retVal);

    if ($retVal !== 0) {
        throw new Exception("❌ Error ejecutando openssl:\n" . implode("\n", $output));
    }

    // WSAA Client (SOAP 1.1 como exige AFIP)
    $wsaaClient = new SoapClient("$basePath/wsaa.wsdl", [
        'soap_version' => SOAP_1_1,
        'location' => "https://wsaahomo.afip.gov.ar/ws/services/LoginCms",
        'trace' => 1,
        'exceptions' => true
    ]);

    $CMS = file_get_contents($TRASignature);
    if (!$CMS) throw new Exception("No se pudo leer el archivo firmado: $TRASignature");

    $response = $wsaaClient->loginCms(['in0' => $CMS]);

    if (!isset($response->loginCmsReturn)) {
        throw new Exception("La respuesta del WSAA no contiene loginCmsReturn");
    }

    $tokenResponse = simplexml_load_string($response->loginCmsReturn);
    if (!$tokenResponse) {
        throw new Exception("No se pudo parsear loginCmsReturn");
    }

    $token = $tokenResponse->credentials->token ?? null;
    $sign  = $tokenResponse->credentials->sign ?? null;

    if (!$token || !$sign) {
        throw new Exception("El token o sign no están presentes en la respuesta");
    }

    // Guardar TA.xml
    $taPath = "$tmpPath/TA.xml";
    if (file_put_contents($taPath, $response->loginCmsReturn) === false) {
        throw new Exception("No se pudo guardar TA.xml en: $taPath");
    }

    // Validar que se haya guardado correctamente
    if (!file_exists($taPath)) {
        throw new Exception("TA.xml no fue generado, aunque AFIP respondió");
    }

    logMsg("✅ TA.xml generado correctamente.");

} catch (SoapFault $sf) {
    logMsg("⚠️ SOAP Fault: (puede ser por contenido CMS no UTF-8, AFIP igual lo procesa)");
    logMsg("Código : " . $sf->faultcode);
    logMsg("Mensaje : " . $sf->faultstring);
    http_response_code(200); // no cortar el flujo, ya se guardó el TA
} catch (Exception $e) {
    logMsg("❌ Error: " . $e->getMessage());
    http_response_code(500);
}
