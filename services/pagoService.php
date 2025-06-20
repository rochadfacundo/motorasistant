<?php
require_once __DIR__ . '/../utils/db.php';
require_once __DIR__ . '/../utils/logger.php';

class PagoService {
    public static function guardarPagoDesdeObjeto($pago): void {
        $pdo = DB::getConnection();

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

        $stmt->execute();

        Logger::logWebhook("💾 Pago insertado en la base de datos correctamente: {$pago->id}");
    }
}
