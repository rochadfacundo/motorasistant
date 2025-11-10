<?php
 /**
 * ================================================================
 * MotorAssistant - Servicio de Gestión de Preferencias
 * ---------------------------------------------------------------
 * Este servicio administra la persistencia y recuperación de las 
 * preferencias de pago creadas en Mercado Pago (Checkout Pro).
 *
 * Funciones principales:
 * 1️ Guardar los datos de una preferencia recién creada.
 * 2️ Obtener los datos de una preferencia por su ID para
 *    vincularlos con el pago real y la factura emitida.
 * ================================================================
 */

class PreferenciaService{

     /**
     * ================================================================
     * guardarPreferencia($data)
     * ---------------------------------------------------------------
     * Inserta una nueva preferencia en la base de datos.
     * Se usa en `pago.php` luego de crear la preferencia en MercadoPago.
     *
     * Campos esperados:
     *  - preference_id: ID único generado por MercadoPago
     *  - tipo_factura: Tipo de comprobante (A, B o C)
     *  - nombre / apellido / email: datos del comprador
     *  - contrato: identificador del servicio
     *  - monto: importe total
     *  - cuit: opcional (solo para Factura A)
     *
     * @param array $data Datos de la preferencia
     * @return bool true si se insertó correctamente, false si hubo error
     * ================================================================
     */
    public static function guardarPreferencia(array $data): bool {
        try {
            $pdo = DB::getConnection();

            // Stored Procedure: insertarPreferencia
            $stmt = $pdo->prepare("CALL insertarPreferencia(
                :pPreferenceId, :pTipoFactura, :pNombre, :pApellido, :pEmail, :pContrato, :pMonto, :pCuit
            )");
            
            // Parámetros
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

     /**
     * ================================================================
     * obtenerPorPreferenceId($preferenceId)
     * ---------------------------------------------------------------
     * Recupera los datos asociados a una preferencia de pago, 
     * necesaria para emitir la factura (nombre, email, tipo, etc.)
     *
     * Este método se utiliza desde:
     *  - `FacturaService::generarYGuardarFactura()`
     * 
     * @param string $preferenceId ID de preferencia de MercadoPago
     * @return array|null Datos de la preferencia o null si no se encontró
     * ================================================================
     */
    public static function obtenerPorPreferenceId($preferenceId): ?array {
        try {
            $pdo = DB::getConnection();

            // Stored Procedure: obtenerDatosPreferencia
            $stmt = $pdo->prepare("CALL obtenerDatosPreferencia(:preference_id)");


            $stmt->bindParam(':preference_id', $preferenceId, PDO::PARAM_STR);
            $stmt->execute();

            // Recuperar una sola fila (cada preferencia es única)
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Liberar el cursor del stored procedure
            $stmt->closeCursor(); 
            return $row ?: null;
        } catch (PDOException $e) {
            Logger::logWebhook("❌ Error al obtener preferencia: " . $e->getMessage());
            return null;
        }
    }
    
}