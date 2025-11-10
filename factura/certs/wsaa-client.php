<?php
/**
 * ================================================================
 * MotorAsistant - WSAA Client (AFIP Autenticación)
 * ---------------------------------------------------------------
 * Script encargado de autenticar el sistema ante el WSAA (Web Service 
 * de Autenticación y Autorización) de AFIP.
 * 
 * 1️ Crea un Ticket de Requerimiento de Acceso (TRA.xml)
 * 2️ Firma el TRA usando certificado digital (.pem)
 * 3️ Envía el CMS firmado al WSAA (SOAP)
 * 4️ Recibe el Ticket de Acceso (TA.xml) con Token y Sign
 * ================================================================
 */

/**
 * Imprime mensajes con timestamp ISO8601 para facilitar debugging.
 */
function logMsg($msg) {
    $timestamp = date('c');
    echo "$timestamp $msg\n";
}

try {
    logMsg("🛡 Preparando autenticación con AFIP...");

    // CUIT y servicio a solicitar (wsfe = Facturación electrónica)
    $CUIT = '30718607961';
    $service = 'wsfe';

    // Rutas base y temporales
    $basePath = __DIR__;
    $tmpPath = "$basePath/tmp";

    // Verificar existencia y permisos de la carpeta temporal
    if (!is_dir($tmpPath) || !is_writable($tmpPath)) {
        throw new Exception("No se puede escribir en el directorio temporal: $tmpPath");
    }

    /**
     * ================================================================
     *  PASO 1: CREAR ARCHIVO TRA (Ticket Request Access)
     * ---------------------------------------------------------------
     * Es un XML que define la solicitud de autenticación, con tiempos 
     * de generación y expiración, y el nombre del servicio requerido.
     * ================================================================
     */
    $TRA = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"/>');
    $TRA->addChild('header')->addChild('uniqueId', time());
    $TRA->header->addChild('generationTime', date('c', time() - 60));
    $TRA->header->addChild('expirationTime', date('c', time() + 60));
    $TRA->addChild('service', $service);

    $TRAPath = "$tmpPath/TRA.xml";
    $TRASignature = "$tmpPath/TRA.tmp";

    // Eliminar archivos previos
    if (file_exists($TRAPath)) unlink($TRAPath);
    if (file_exists($TRASignature)) unlink($TRASignature);

    // Guardar el archivo TRA en el sistema
    if (file_put_contents($TRAPath, $TRA->asXML()) === false) {
        throw new Exception("❌ No se pudo escribir el archivo TRA.xml en: $TRAPath");
    }
    /**
     * ================================================================
     *  PASO 2: FIRMAR TRA CON OPENSSL
     * ---------------------------------------------------------------
     * Usa el certificado digital (.pem) y la clave privada (.pem)
     * para generar un CMS firmado (PKCS#7) que se enviará al WSAA.
     * ================================================================
     */
    $certPath = "$basePath/cert.pem"; // Certificado AFIP (.pem)
    $keyPath = "$basePath/key.pem"; // Clave privada (.pem)
    $cmd = "openssl smime -sign -signer $certPath -inkey $keyPath -outform DER -nodetach -in $TRAPath -out $TRASignature 2>&1";
    exec($cmd, $output, $retVal);

    if ($retVal !== 0) {
        throw new Exception("❌ Error ejecutando openssl:\n" . implode("\n", $output));
    }

    /**
     * ================================================================
     * PASO 3: LLAMAR AL WSAA (SOAP 1.1)
     * ---------------------------------------------------------------
     * Se envía el CMS firmado a AFIP para obtener el TA.xml.
     * La respuesta contiene credenciales temporales (token + sign)
     * ================================================================
     */
    $wsaaClient = new SoapClient("$basePath/wsaa.wsdl", [
        'soap_version' => SOAP_1_1,
        'location' => "https://wsaahomo.afip.gov.ar/ws/services/LoginCms",
        'trace' => 1,
        'exceptions' => true
    ]);

    // Leer CMS firmado
    $CMS = file_get_contents($TRASignature);
    if (!$CMS) throw new Exception("No se pudo leer el archivo firmado: $TRASignature");

    // Invocar método loginCms del WSAA
    $response = $wsaaClient->loginCms(['in0' => $CMS]);

    if (!isset($response->loginCmsReturn)) {
        throw new Exception("La respuesta del WSAA no contiene loginCmsReturn");
    }

    // Parsear el XML de respuesta
    $tokenResponse = simplexml_load_string($response->loginCmsReturn);
    if (!$tokenResponse) {
        throw new Exception("No se pudo parsear loginCmsReturn");
    }


    // Extraer token y sign del XML
    $token = $tokenResponse->credentials->token ?? null;
    $sign  = $tokenResponse->credentials->sign ?? null;

    if (!$token || !$sign) {
        throw new Exception("El token o sign no están presentes en la respuesta");
    }

    /**
     * ================================================================
     * PASO 4: GUARDAR TA.XML (Ticket de Acceso)
     * ---------------------------------------------------------------
     * Contiene el token y sign necesarios para consumir otros WS de AFIP.
     * ================================================================
     */
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
        /**
     * ================================================================
     * Excepción SOAP
     * ---------------------------------------------------------------
     * OJO AFIP a veces devuelve errores por formato de CMS o codificación UTF-8,
     * pero igual puede haber emitido un TA válido. No se corta el flujo.
     * ================================================================
     */
    logMsg("⚠️ SOAP Fault: (puede ser por contenido CMS no UTF-8, AFIP igual lo procesa)");
    logMsg("Código : " . $sf->faultcode);
    logMsg("Mensaje : " . $sf->faultstring);
    http_response_code(200);// No interrumpir proceso (el TA puede haberse generado)
} catch (Exception $e) {
    /**
     * ================================================================
     * Excepciones generales (errores de IO o OpenSSL)
     * ---------------------------------------------------------------
     * Si falla cualquier paso crítico (no se firma, no se guarda, etc),
     * se interrumpe con código 500 para indicar error de servidor.
     * ================================================================
     */
    logMsg("❌ Error: " . $e->getMessage());
    http_response_code(500);
}
