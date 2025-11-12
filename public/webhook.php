<?php
/**
 * ================================================================
 * MotorAssistant - Webhook de Mercado Pago
 * ---------------------------------------------------------------
 * Este script recibe las notificaciones automáticas (webhooks)
 * enviadas por los servidores de Mercado Pago cuando ocurre
 * un evento de pago, reembolso, actualización de estado, etc.
 *
 * Flujo:
 * 1️ Mercado Pago envía un POST con JSON (type, data.id, etc.)
 * 2️ Este endpoint delega el procesamiento al PagoController
 * 3️ PagoController consulta la API de MP y genera factura si aplica
 * 4️ Se responde con código HTTP 200 para confirmar la recepción
 * ================================================================
 */

// Cargar el controlador principal de pagos
require_once __DIR__ . '/../controller/PagoController.php';

 /**
 * ================================================================
 *  Procesar notificación entrante
 * ---------------------------------------------------------------
 * PagoController::procesarWebhook()
 *   - Lee el cuerpo JSON recibido
 *   - Valida el tipo de evento ("payment")
 *   - Consulta los detalles del pago en la API de MP
 *   - Guarda en la BD y genera factura si el pago fue aprobado
 * ================================================================
 */
PagoController::procesarWebhook();

 /**
 * ================================================================
 * Responder a Mercado Pago
 * ---------------------------------------------------------------
 * Aunque PagoController ya envía su propio código HTTP según
 * resultado interno, dejo un 200 final para asegurar que
 * MP no reintente innecesariamente la notificación.
 * ================================================================
 */
http_response_code(200);