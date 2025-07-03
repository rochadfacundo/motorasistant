<?php

class FileUtils
{
    public static function copiarFacturaAPublico(string $origen): ?string
    {
        $publicDir = __DIR__ . '/../public/facturas/';
        $destino = $publicDir . basename($origen);

        Logger::logWebhook("📤 Intentando copiar factura:");
        Logger::logWebhook("📄 Origen: $origen");
        Logger::logWebhook("📁 Destino: $destino");

        // Crear la carpeta si no existe
        if (!is_dir($publicDir)) {
            if (!mkdir($publicDir, 0775, true)) {
                Logger::logWebhook("❌ No se pudo crear la carpeta pública: $publicDir");
                return null;
            }
            Logger::logWebhook("📁 Carpeta 'public/facturas' creada automáticamente.");
        }

        // Verificar permisos del archivo origen
        if (!file_exists($origen)) {
            Logger::logWebhook("❌ El archivo origen no existe: $origen");
            return null;
        }

        if (!is_readable($origen)) {
            Logger::logWebhook("❌ El archivo origen NO es legible: $origen");
        } else {
            Logger::logWebhook("✅ El archivo origen es legible.");
        }

        // Verificar si la carpeta es escribible
        if (!is_writable($publicDir)) {
            Logger::logWebhook("❌ La carpeta '$publicDir' NO es escribible.");
            return null;
        } else {
            Logger::logWebhook("✅ La carpeta destino es escribible.");
        }

        // Intentar copiar
        if (!copy($origen, $destino)) {
            Logger::logWebhook("❌ Error al copiar factura de '$origen' a '$destino'");
            return null;
        }

        Logger::logWebhook("✅ Factura copiada a carpeta pública correctamente: $destino");
        return $destino;
    }
}
