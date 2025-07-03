<?php

class PreferenciaService{

    public static function guardarPreferencia(array $data): bool {
        try {
            $pdo = DB::getConnection();
            $stmt = $pdo->prepare("CALL insertarPreferencia(
                :pPreferenceId, :pTipoFactura, :pNombre, :pApellido, :pEmail, :pContrato, :pMonto, :pCuit
            )");
            
            $stmt->execute([
                ':pPreferenceId' => $data['preference_id'],
                ':pTipoFactura'  => $data['tipo_factura'],
                ':pNombre'       => $data['nombre'],
                ':pApellido'     => $data['apellido'],
                ':pEmail'        => $data['email'],
                ':pContrato'     => $data['contrato'],
                ':pMonto'        => $data['monto'],
                ':pCuit'         => $data['cuit'] ?? null
            ]);

            return true;
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al guardar preferencia: " . $e->getMessage());
            return false;
        }
    }


    public static function obtenerPorPreferenceId($preferenceId): ?array {
        try {
            $pdo = DB::getConnection();
            $stmt = $pdo->prepare("CALL obtenerDatosPreferencia(:preference_id)");
            $stmt->bindParam(':preference_id', $preferenceId, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor(); // Importante para liberar el result set
            return $row ?: null;
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al obtener preferencia: " . $e->getMessage());
            return null;
        }
    }
    
}