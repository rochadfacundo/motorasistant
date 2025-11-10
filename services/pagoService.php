<?php
 /**
 * ================================================================
 * MotorAssistant - Servicio de Persistencia de Pagos
 * ---------------------------------------------------------------
 * Este servicio encapsula la lógica de almacenamiento de pagos
 * recibidos desde MercadoPago. Se utiliza tanto en el Webhook como
 * en los controladores de éxito (`success.php`).
 *
 * Flujo:
 * 1️ Recibe el objeto de pago completo desde la API de MP.
 * 2️ Extrae los campos relevantes.
 * 3️ Invoca el Stored Procedure `insertarPago`.
 * 4️ Registra el resultado en el log del sistema.
 * ================================================================
 */

require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/logger.php';

class PagoService {

     /**
     * ================================================================
     * guardarPagoDesdeObjeto($pago)
     * ---------------------------------------------------------------
     * Inserta en la base de datos un pago confirmado proveniente de
     * MercadoPago, utilizando el stored procedure `insertarPago`.
     *
     * @param object $pago Objeto completo retornado por la API de MP.
     * ================================================================
     */
    public static function guardarPagoDesdeObjeto($pago): void {

        // Obtener conexión activa a la base de datos (PDO)
        $pdo = DB::getConnection();

         /**
         * ================================================================
         * Llamada al Stored Procedure `insertarPago`
         * ---------------------------------------------------------------
         * Se insertan los datos esenciales del pago:
         *  - IDs y estados de MercadoPago
         *  - Referencia externa (enlaza con preferencia / contrato)
         *  - Tipo de pago (tarjeta, transferencia, etc.)
         *  - ID de orden y preferencia (para trazabilidad)
         * ================================================================
         */
        $stmt = $pdo->prepare("CALL insertarPago(
            :collection_id,
            :collection_status,
            :payment_id,
            :status,
            :external_reference,
            :payment_type,
            :merchant_order_id,
            :preference_id,
            :site_id,
            :processing_mode,
            :merchant_account_id
        )");

        // Vincular parámetros a partir del objeto de pago
        $stmt->bindParam(':collection_id', $pago->id); // collection_id = payment_id
        $stmt->bindParam(':collection_status', $pago->status);
        $stmt->bindParam(':payment_id', $pago->id);
        $stmt->bindParam(':status', $pago->status);
        $stmt->bindParam(':external_reference', $pago->external_reference);
        $stmt->bindParam(':payment_type', $pago->payment_type_id);
        $stmt->bindParam(':merchant_order_id', $pago->order->id ?? null);
        $stmt->bindParam(':preference_id', $pago->preference_id);
        $stmt->bindParam(':site_id', $pago->site_id ?? null);
        $stmt->bindParam(':processing_mode', $pago->processing_mode ?? null);
        $stmt->bindParam(':merchant_account_id', $pago->merchant_account_id ?? null);

        // Ejecutar el procedimiento
        $stmt->execute();

        // Registrar en log para trazabilidad
        Logger::logWebhook("Pago insertado en la base de datos correctamente: {$pago->id}");
    }
}
