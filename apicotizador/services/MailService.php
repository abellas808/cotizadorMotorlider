<?php

class MailService
{
    public function enviarConfirmacionCotizacion(array $clienteData, array $resultado): void
    {
        $this->enviarMailCliente($clienteData, $resultado);
        $this->enviarMailInterno($clienteData, $resultado);
    }

    private function enviarMailCliente(array $clienteData, array $resultado): void
    {
        $from = getenv('COTIZADOR_MAIL_FROM') ?: 'no-reply@motorlider.com.uy';
        $to   = trim((string)($clienteData['email'] ?? ''));

        if ($to === '') {
            return;
        }

        $nombre     = trim((string)($clienteData['nombre'] ?? ''));
        $nombreAuto = trim((string)($clienteData['nombre_auto'] ?? 'tu vehículo'));

        $mensajeCliente = trim((string)(
            $resultado['msg_cliente']
            ?? $resultado['msg']
            ?? 'Recibimos tu solicitud de cotización y nos comunicaremos contigo a la brevedad.'
        ));

        $subject = 'Motorlider - Recibimos tu solicitud de cotización';

        $body  = "Hola " . ($nombre !== '' ? $nombre : 'cliente') . ",\n\n";
        $body .= "Recibimos tu solicitud de cotización.\n";
        $body .= "Vehículo: " . $nombreAuto . "\n\n";
        $body .= $this->htmlToText($mensajeCliente) . "\n\n";
        $body .= "Nos comunicaremos contigo a la brevedad.\n\n";
        $body .= "Saludos,\n";
        $body .= "Motorlider\n";

        $headers  = "From: {$from}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail($to, $subject, $body, $headers);
    }

    private function enviarMailInterno(array $clienteData, array $resultado): void
    {
        $from      = getenv('COTIZADOR_MAIL_FROM') ?: 'no-reply@motorlider.com.uy';
        $toInterno = trim((string)(getenv('COTIZADOR_MAIL_TEST_TO') ?: ''));

        if ($toInterno === '') {
            return;
        }

        $subject = 'Motorlider - Nueva solicitud de cotización';

        $valorFinal = $this->obtenerValorFinal($resultado);

        $body  = "Nueva solicitud de cotización\n";
        $body .= "====================================\n\n";

        $body .= "CLIENTE\n";
        $body .= "Nombre: " . ($clienteData['nombre'] ?? '') . "\n";
        $body .= "Email: " . ($clienteData['email'] ?? '') . "\n";
        $body .= "Teléfono: " . ($clienteData['telefono'] ?? '') . "\n\n";

        $body .= "VEHÍCULO\n";
        $body .= "Auto: " . ($clienteData['nombre_auto'] ?? '') . "\n";
        $body .= "Marca: " . ($clienteData['brand'] ?? $clienteData['marca'] ?? '') . "\n";
        $body .= "Modelo: " . ($clienteData['modelo'] ?? '') . "\n";
        $body .= "Año: " . ($clienteData['anio'] ?? '') . "\n";
        $body .= "KM: " . ($clienteData['km'] ?? '') . "\n";
        $body .= "Valor pretendido: " . ($clienteData['valor_pretendido'] ?? '') . "\n\n";

        $body .= "RESULTADO\n";
        $body .= "OK: " . (!empty($resultado['ok']) ? 'SI' : 'NO') . "\n";
        $body .= "Mensaje: " . ($resultado['msg'] ?? '') . "\n";
        $body .= "Comparables: " . ($resultado['comparables'] ?? $resultado['count'] ?? 0) . "\n";
        $body .= "Min: " . ($resultado['min'] ?? '') . "\n";
        $body .= "Max: " . ($resultado['max'] ?? '') . "\n";
        $body .= "Prom: " . ($resultado['avg'] ?? '') . "\n";
        $body .= "Median: " . ($resultado['median'] ?? '') . "\n";
        $body .= "Valor final enviado al cliente: " . ($valorFinal !== null ? $this->formatNumber($valorFinal) : '') . "\n";

        if (!empty($resultado['valor_minimo_motorlider'])) {
            $body .= "Valor mínimo Motorlider: " . $resultado['valor_minimo_motorlider'] . "\n";
        }
        if (!empty($resultado['valor_maximo_motorlider'])) {
            $body .= "Valor máximo Motorlider: " . $resultado['valor_maximo_motorlider'] . "\n";
        }
        if (!empty($resultado['valor_promedio_motorlider'])) {
            $body .= "Valor promedio Motorlider: " . $resultado['valor_promedio_motorlider'] . "\n";
        }

        $headers  = "From: {$from}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        @mail($toInterno, $subject, $body, $headers);
    }

    private function obtenerValorFinal(array $resultado): ?float
    {
        $candidatos = [
            $resultado['valor_final'] ?? null,
            $resultado['valor_promedio_motorlider'] ?? null,
            $resultado['avg'] ?? null,
        ];

        foreach ($candidatos as $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }

            if (is_numeric($valor)) {
                return (float)$valor;
            }

            $normalizado = str_replace(',', '.', preg_replace('/[^\d,\.]/', '', (string)$valor));
            if ($normalizado !== '' && is_numeric($normalizado)) {
                return (float)$normalizado;
            }
        }

        return null;
    }

    private function formatNumber($value): string
    {
        $n = (float)$value;
        return number_format($n, 0, ',', '.');
    }

    private function htmlToText(string $text): string
    {
        $text = str_replace(
            ['<br>', '<br/>', '<br />', '</p>', '<p>', '<strong>', '</strong>'],
            ["\n", "\n", "\n", "\n", '', '', ''],
            $text
        );

        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text);
    }                                                                           
}