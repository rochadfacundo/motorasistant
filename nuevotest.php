<?php
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.mercadopago.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);

if(curl_errno($ch)){
    echo 'Error: ' . curl_error($ch);
} else {
    echo 'Conexión OK';
}

curl_close($ch);
