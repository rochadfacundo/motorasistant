<?php

 /**
 * ================================================================
 * MotorAssistant - Utilidades de archivos (Facturas PDF)
 * ---------------------------------------------------------------
 * Clase auxiliar que gestiona la copia y disponibilidad de archivos
 * PDF de facturas generadas por el sistema.
 *
 * Función principal:
 *  - Copiar el archivo PDF generado por Dompdf desde una ubicación 
 *    interna (no pública) hacia el directorio público accesible 
 *    desde el navegador: `/public/facturas/`
 * ================================================================
 */
class FileUtils
{
     /**
     * ================================================================
     * copiarFacturaAPublico($origen)
     * ---------------------------------------------------------------
     * Copia la factura PDF generada a la carpeta pública del proyecto,
     * validando existencia, permisos y rutas antes de proceder.
     *
     * Parámetros:
     *  - $origen: Ruta absoluta del PDF generado por GeneradorPDF
     *
     * Retorna:
     *  - string|null Ruta de destino del archivo copiado
     *    o null si falló el proceso.
     * ================================================================
     */
    public static function copiarFacturaAPublico(string $origen): ?string
    {
        // Carpeta destino (dentro de /public/facturas)
        $publicDir = __DIR__ . '/../public/facturas/';
        $destino = $publicDir . basename($origen);

        Logger::logWebhook("📤 Intentando copiar factura:");
        Logger::logWebhook("📄 Origen: $origen");
        Logger::logWebhook("📁 Destino: $destino");

         /**
         * ================================================================
         * Paso 1 - Verificar y crear carpeta pública si no existe
         * ---------------------------------------------------------------
         */
        if (!is_dir($publicDir)) {
            if (!mkdir($publicDir, 0775, true)) {
                Logger::logWebhook("❌ No se pudo crear la carpeta pública: $publicDir");
                return null;
            }
            Logger::logWebhook("📁 Carpeta 'public/facturas' creada automáticamente.");
        }

         /**
         * ================================================================
         * Paso 2️ - Validar existencia y permisos del archivo origen
         * ---------------------------------------------------------------
         */
        if (!file_exists($origen)) {
            Logger::logWebhook("❌ El archivo origen no existe: $origen");
            return null;
        }

        if (!is_readable($origen)) {
            Logger::logWebhook("❌ El archivo origen NO es legible: $origen");
        } else {
            Logger::logWebhook("✅ El archivo origen es legible.");
        }

         /**
         * ================================================================
         * Paso 3️ - Validar permisos de escritura en destino
         * ---------------------------------------------------------------
         */
        if (!is_writable($publicDir)) {
            Logger::logWebhook("❌ La carpeta '$publicDir' NO es escribible.");
            return null;
        } else {
            Logger::logWebhook("✅ La carpeta destino es escribible.");
        }

         /**
         * ================================================================
         * Paso 4️ - Copiar el archivo
         * ---------------------------------------------------------------
         * La función copy() reemplaza el archivo si ya existe.
         * ================================================================
         */
        if (!copy($origen, $destino)) {
            Logger::logWebhook("❌ Error al copiar factura de '$origen' a '$destino'");
            return null;
        }

        // Asignar permisos estándar (lectura y escritura para owner/grupo)
        chmod($destino, 0664);

        Logger::logWebhook("✅ Factura copiada a carpeta pública correctamente: $destino");
        return $destino;
    }
}
