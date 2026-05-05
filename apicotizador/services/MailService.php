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
        $toInternoRaw = trim((string)(getenv('COTIZADOR_MAIL_TEST_TO') ?: ''));

        $toInternos = array_filter(array_map('trim', explode(',', $toInternoRaw)));

        if (empty($toInternos)) {
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
        $body .= "Min: " . ($resultado['min'] ?? '') . "\n";
        $body .= "Max: " . ($resultado['max'] ?? '') . "\n";
        $body .= "Prom: " . ($resultado['avg'] ?? '') . "\n";
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

        foreach ($toInternos as $toInterno) {
            if (filter_var($toInterno, FILTER_VALIDATE_EMAIL)) {
                @mail($toInterno, $subject, $body, $headers);
            }
        }
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
    
    public function enviarSoloMailInternoCotizacion(array $clienteData, array $resultado): void
    {
        //$this->enviarMailInterno($clienteData, $resultado);
    }

    public function enviarMailInternoAgenda(array $clienteData, array $agendaData, array $resultado = []): array
    {
        $from = getenv('COTIZADOR_MAIL_FROM') ?: 'no-reply@motorlider.com.uy';

        $toInternos = [
            'aramos@motorlider.com.uy',
            'lara.motorlider@gmail.com',
            'kevin.fernandez@motorlider.com.uy',
            'sebastian.motorlider@gmail.com',
            'fernandomotorlideruy@gmail.com',
            'info@motorlider.com.uy',
            'abella.motorlider@gmail.com'
        ];

        $fecha = (string)($agendaData['fecha'] ?? '');
        $hora  = substr((string)($agendaData['hora'] ?? ''), 0, 5);

        $nombre = trim((string)($clienteData['nombre'] ?? ''));
        $marca  = trim((string)($clienteData['marca'] ?? ''));
        $modelo = trim((string)($clienteData['modelo'] ?? ''));

       $autoAsunto = trim((string)($clienteData['auto'] ?? $clienteData['nombre_auto'] ?? trim($marca . ' ' . $modelo)));

        $subject = "AGENDA | {$fecha} {$hora} | {$nombre} | {$autoAsunto}";
        $telefono = str_replace('whatsapp:', '', (string)($clienteData['telefono'] ?? ''));

        $body  = "AGENDA CONFIRMADA\n";
        $body .= "====================================\n\n";

        $body .= "CLIENTE\n";
        $body .= "Nombre: " . $nombre . "\n";
        $body .= "Email: " . ($clienteData['email'] ?? '') . "\n";
        $body .= "Teléfono: " . $telefono . "\n\n";

        $body .= "VEHÍCULO\n";
        $body .= "Auto: " . ($clienteData['auto'] ?? $clienteData['nombre_auto'] ?? '') . "\n";
        $body .= "Marca: " . $marca . "\n";
        $body .= "Modelo: " . $modelo . "\n";
        $body .= "Año: " . ($clienteData['anio'] ?? '') . "\n";
        $body .= "KM: " . ($clienteData['km'] ?? '') . "\n";
        $body .= "Valor pretendido: " . ($clienteData['valor_pretendido'] ?? '') . "\n\n";

        $body .= "AGENDA\n";
        $body .= "Fecha: " . $fecha . "\n";
        $body .= "Hora: " . $hora . "\n\n";

        $body .= "RESULTADO\n";
        $body .= "OK: SI\n";
        $body .= "Min: " . ($resultado['min'] ?? '') . "\n";
        $body .= "Max: " . ($resultado['max'] ?? '') . "\n";
        $body .= "Prom: " . ($resultado['avg'] ?? '') . "\n";
        $body .= "Valor mínimo Motorlider: " . ($resultado['valor_minimo_motorlider'] ?? '') . "\n";
        $body .= "Valor máximo Motorlider: " . ($resultado['valor_maximo_motorlider'] ?? '') . "\n";
        $body .= "Valor promedio Motorlider: " . ($resultado['valor_promedio_motorlider'] ?? '') . "\n";

        $headers  = "From: {$from}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

       $enviados = 0;
        $errores = [];

        foreach ($toInternos as $toInterno) {
            if (!filter_var($toInterno, FILTER_VALIDATE_EMAIL)) {
                $errores[] = "Email inválido: " . $toInterno;
                continue;
            }

            $ok = mail($toInterno, $subject, $body, $headers);

            if ($ok) {
                $enviados++;
            } else {
                $errores[] = "mail() devolvió false para: " . $toInterno;
            }
        }

        return [
            'ok' => $enviados > 0,
            'enviados' => $enviados,
            'destinatarios' => $toInternos,
            'errores' => $errores
        ];
    }
}