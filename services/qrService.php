<?php
class QrService {
    public static function generarUrlQrAfip(array $params): string {
        $data = [
            "ver" => 1,
            "fecha" => date('Y-m-d'),
            "cuit" => (int)$params['cuit'],
            "ptoVta" => (int)$params['ptoVta'],
            "tipoCmp" => (int)$params['tipoCmp'], // Asegurar que sea int
            "nroCmp" => (int)$params['nroCmp'],
            "importe" => round($params['importe'], 2),
            "moneda" => "PES",
            "ctz" => 1,
            "tipoDocRec" => 99,
            "nroDocRec" => 0,
            "tipoCodAut" => "E",
            "codAut" => (string)$params['cae']
        ];

        Logger::logWebhook("📤 JSON para QR:\n" . json_encode($data, JSON_PRETTY_PRINT));

        $base64 = base64_encode(json_encode($data, JSON_UNESCAPED_SLASHES));
        return "https://www.afip.gob.ar/fe/qr/?p=" . $base64;
    }
}
