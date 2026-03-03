<?php

class MailService
{
    public function enviarConfirmacionCotizacion(array $clienteData, array $resultado): void
    {
        $modoTest = getenv('COTIZADOR_MAIL_TEST') === '1';
        $toTest   = getenv('COTIZADOR_MAIL_TEST_TO') ?: '';
        $from     = getenv('COTIZADOR_MAIL_FROM') ?: 'no-reply@motorlider.com.uy';

        $to = $modoTest ? $toTest : ($clienteData['email'] ?? '');

        if (!$to) return;

        $subject = "Motorlider - Solicitud de Cotización recibida";

        $body  = "Hola " . ($clienteData['nombre'] ?? '👋') . ",\n\n";
        $body .= "Recibimos tu solicitud de cotización.\n";
        $body .= "Nos comunicaremos con usted a la brevedad.\n\n";

        if ($modoTest) {
            $body .= "---- MODO TEST ----\n";
            $body .= "Auto: " . ($clienteData['nombre_auto'] ?? '') . "\n";
            $body .= "Marca: " . ($clienteData['brand'] ?? '') . "\n";
            $body .= "Modelo: " . ($clienteData['modelo'] ?? '') . "\n";
            $body .= "Año: " . ($clienteData['anio'] ?? '') . "\n";
            $body .= "KM: " . ($clienteData['km'] ?? '') . "\n\n";

            if (!empty($resultado['ok'])) {
                $body .= "Comparables: " . ($resultado['comparables'] ?? 0) . "\n";
                $body .= "Min: " . ($resultado['min'] ?? '') . "\n";
                $body .= "Max: " . ($resultado['max'] ?? '') . "\n";
                $body .= "Prom: " . ($resultado['avg'] ?? '') . "\n";
                $body .= "Median: " . ($resultado['median'] ?? '') . "\n";
            } else {
                $body .= "Resultado: FALLÓ -> " . ($resultado['msg'] ?? 'sin msg') . "\n";
            }
            $body .= "\n-------------------\n";
        }

        $headers = "From: {$from}\r\n";

        @mail($to, $subject, $body, $headers);
    }
}
