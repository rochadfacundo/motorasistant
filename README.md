# MotorAssistant - Sistema de Facturación Electrónica Integrada

MotorAssistant es una plataforma desarrollada en PHP que automatiza la **generación de facturas electrónicas** ante AFIP tras
la **verificación de pagos aprobados en MercadoPago**.
Ideal para empresas que necesitan emitir facturas de forma inmediata y confiable al confirmar un pago online.

---

## Descripción

Este sistema permite:

- Obtener pagos realizados en MercadoPago por su ID.
- Verificar automáticamente si el pago fue aprobado.
- Autenticarse ante AFIP utilizando certificados digitales y WSAA.
- Emitir comprobantes electrónicos (Factura A, B o C) mediante WSFE.
- Generar el archivo `TA.xml` con token de acceso cuando sea necesario.
- Crear un PDF de la factura con los datos del cliente y del pago.
- Registrar todas las operaciones en archivos de log.
- Verificar si un pago ya fue facturado (con posibilidad de usar base de datos).
- Integración preparada para trabajar con procedimientos almacenados en MySQL.

---

## ⚙️ Requisitos

- PHP >= 8.0
- XAMPP o similar
- Extensiones `soap` y `openssl` habilitadas
- Composer
- Certificados digitales de AFIP (modo homologación o producción)
- Cuenta de MercadoPago con `access_token`
