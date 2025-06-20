<?php
require_once __DIR__ . '/../controller/PagoController.php';
PagoController::procesarWebhook();
http_response_code(200);
