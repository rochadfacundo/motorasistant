#!/usr/bin/php
<?php
# Autor: Gerardo Fisanotti - AFIP
# Adaptado para Averia Motor S.R.L. - MotorAssistance

define ("WSDL", "wsaa.wsdl");              // Archivo WSDL del WSAA
define ("CERT", "certificado.crt");        // Certificado en formato PEM (X.509)
define ("PRIVATEKEY", "averia.key"); // Clave privada en formato PEM
define ("PASSPHRASE", "");                 // Si la clave no tiene contraseña, dejar vacío
define ("PROXY_HOST", "");                 // Sin proxy
define ("PROXY_PORT", "");                 // Sin proxy
define ("URL", "https://wsaahomo.afip.gov.ar/ws/services/LoginCms"); // URL del entorno de testing

#==============================================================================
function CreateTRA($SERVICE)
{
  $TRA = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"></loginTicketRequest>');
  $TRA->addChild('header');
  $TRA->header->addChild('uniqueId', date('U'));
  $TRA->header->addChild('generationTime', date('c', time() - 60));
  $TRA->header->addChild('expirationTime', date('c', time() + 60));
  $TRA->addChild('service', $SERVICE);
  $TRA->asXML('TRA.xml');
}

// === FIRMA EL TRA.xml CON PKCS#7 Y DEVUELVE EL CMS ===
function SignTRA()
{
    $cmd = 'openssl smime -sign -signer ' . CERT . ' -inkey ' . PRIVATEKEY .
           ' -in TRA.xml -out TRA.tmp -outform DER -nodetach 2>&1';
    $output = [];
    $return = 0;

    exec($cmd, $output, $return);
    if ($return !== 0 || !file_exists("TRA.tmp")) {
        echo "❌ Error ejecutando openssl:\n" . implode("\n", $output) . "\n";
        exit(1);
    }

    // Codifica el archivo a Base64
    $base64 = base64_encode(file_get_contents("TRA.tmp"));
    unlink("TRA.tmp");

    if (strlen($base64) < 1000) {
        exit("❌ Error: el CMS generado es inválido o muy corto\n");
    }

    return $base64;
}


// === ENVÍA EL CMS FIRMADO AL WSAA Y DEVUELVE EL TA.xml ===
function CallWSAA($CMS)
{
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false
        ]
    ]);

    $client = new SoapClient(WSDL, array(
        'location'           => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
        'soap_version'       => SOAP_1_2,
        'stream_context'     => $context,
        'trace'              => 1,
        'exceptions'         => true,
        'connection_timeout' => 30
    ));

    try {
        $results = $client->loginCms(array('in0' => $CMS));
        file_put_contents("request-loginCms.xml", $client->__getLastRequest());
        file_put_contents("response-loginCms.xml", $client->__getLastResponse());
        return $results->loginCmsReturn;
    } catch (SoapFault $e) {
        file_put_contents("request-loginCms.xml", $client->__getLastRequest());
        file_put_contents("response-loginCms.xml", $client->__getLastResponse());

        echo "❌ SOAP Fault:\n";
        echo "Mensaje : " . $e->getMessage() . "\n";
        echo "Código  : " . $e->getCode() . "\n";
        echo "Archivo : " . $e->getFile() . "\n";
        echo "Línea   : " . $e->getLine() . "\n";
        echo "Traza   :\n" . $e->getTraceAsString() . "\n";
        exit(1);
    }
}

#==============================================================================
function ShowUsage($MyPath)
{
  printf("Uso  : %s Arg#1\n", $MyPath);
  printf("donde: Arg#1 debe ser el nombre del servicio WS (ej: wsfe, wsmtxca, etc).\n");
}

#==============================================================================
ini_set("soap.wsdl_cache_enabled", "0");
if (!file_exists(CERT)) { exit("No se encontró el archivo de certificado: ".CERT."\n"); }
if (!file_exists(PRIVATEKEY)) { exit("No se encontró la clave privada: ".PRIVATEKEY."\n"); }
if (!file_exists(WSDL)) { exit("No se encontró el archivo WSDL: ".WSDL."\n"); }

if ($argc < 2) {
  ShowUsage($argv[0]);
  exit();
}

$SERVICE = $argv[1];
CreateTRA($SERVICE);
$CMS = SignTRA();
$TA = CallWSAA($CMS);
if (!file_put_contents("TA.xml", $TA)) {
  exit("No se pudo escribir el archivo TA.xml\n");
}

echo "✅ TA.xml generado correctamente\n";
?>
