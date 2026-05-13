<?php

namespace App\Services\Vies;

use SoapFault;

class ViesLookupService
{
    private const WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    private const VIES_INVALID_HINT_PT = 'Um NIF português pode estar correto para Finanças e mesmo assim não constar no VIES: este serviço só cobre contribuintes com número de IVA válido para operações intracomunitárias. Particulares e muitas empresas sem esse registo obtêm este resultado. Compare no verificador oficial da UE e, em caso de dúvida, confirme junto das Finanças se tem NIF de IVA ativo para o comércio intracomunitário.';

    /**
     * @return array{valid: bool, name: ?string, address: ?string, request_date: ?string, fault: ?string, hint: ?string}
     */
    public function check(string $countryCode, string $vatNumber): array
    {
        $countryCode = strtoupper(preg_replace('/\s+/', '', $countryCode));
        $vatNumber = $this->normalizeVatNumber($countryCode, $vatNumber);

        if (strlen($countryCode) !== 2 || $vatNumber === '') {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'Código de país ou NIF inválido.',
                'hint' => null,
            ];
        }

        if ($countryCode === 'PT' && strlen($vatNumber) !== 9) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'Para Portugal, indique o NIF com 9 dígitos (sem prefixo PT).',
                'hint' => null,
            ];
        }

        if (! extension_loaded('soap')) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'A extensão PHP SOAP não está disponível no servidor.',
                'hint' => null,
            ];
        }

        try {
            $client = new \SoapClient(self::WSDL, [
                'connection_timeout' => 15,
                'cache_wsdl' => WSDL_CACHE_DISK,
                'stream_context' => stream_context_create([
                    'http' => [
                        'timeout' => 20,
                        'header' => "User-Agent: Laravel-VIES-Lookup/1.0\r\nAccept: text/xml,application/xml\r\n",
                    ],
                ]),
            ]);

            $result = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber,
            ]);

            $row = $this->soapResultToArray($result);
            $valid = $this->soapBool($row['valid'] ?? false);

            $requestDate = $row['requestDate'] ?? $row['request_date'] ?? null;

            return [
                'valid' => $valid,
                'name' => $valid ? $this->sanitizeViesText($this->soapString($row['name'] ?? null)) : null,
                'address' => $valid ? $this->sanitizeViesText($this->soapString($row['address'] ?? null)) : null,
                'request_date' => $this->soapString($requestDate),
                'fault' => $valid ? null : ($countryCode === 'PT'
                    ? 'O VIES não reconhece este NIF como número de IVA intracomunitário.'
                    : 'Este número não está registado para IVA no VIES (ou não é válido na consulta).'),
                'hint' => $valid ? null : ($countryCode === 'PT' ? self::VIES_INVALID_HINT_PT : null),
            ];
        } catch (SoapFault $e) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => $this->formatSoapFault($e),
                'hint' => null,
            ];
        } catch (\Throwable) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'Erro ao contactar o serviço VIES.',
                'hint' => null,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function soapResultToArray(object $result): array
    {
        $encoded = json_encode($result);
        $decoded = is_string($encoded) ? json_decode($encoded, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function soapString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof \SimpleXMLElement) {
            return trim((string) $value);
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private function normalizeVatNumber(string $countryCode, string $vatNumber): string
    {
        $vat = preg_replace('/\s+/u', '', $vatNumber) ?? '';
        $vatUpper = strtoupper($vat);
        $cc = strtoupper($countryCode);
        if ($cc !== '' && str_starts_with($vatUpper, $cc)) {
            $vat = substr($vat, strlen($cc));
        }

        if ($cc === 'PT') {
            return preg_replace('/\D/', '', $vat) ?? '';
        }

        return preg_replace('/[^A-Za-z0-9]/', '', $vat) ?? '';
    }

    /**
     * SOAP xs:boolean may arrive as string "false", which must not be cast with (bool) in PHP.
     */
    private function soapBool(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value)) {
            return $value !== 0;
        }
        if (is_float($value)) {
            return $value !== 0.0;
        }
        if (is_string($value)) {
            return filter_var(trim($value), FILTER_VALIDATE_BOOLEAN);
        }
        if ($value instanceof \SimpleXMLElement) {
            return filter_var(trim((string) $value), FILTER_VALIDATE_BOOLEAN);
        }

        return (bool) $value;
    }

    private function sanitizeViesText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }
        $t = trim(preg_replace("/\r\n|\r|\n/", "\n", $text) ?? '');
        if ($t === '' || strtoupper($t) === '---') {
            return null;
        }

        return $t;
    }

    private function formatSoapFault(SoapFault $e): string
    {
        $code = strtoupper((string) ($e->faultcode ?? ''));
        $msg = $e->getMessage();
        $combined = strtoupper($code.' '.$msg);

        if (
            str_contains($combined, 'MS_UNAVAILABLE')
            || str_contains($combined, 'SERVICE_UNAVAILABLE')
            || str_contains($combined, 'TIMEOUT')
            || str_contains($combined, 'ECONNRESET')
        ) {
            return 'O serviço VIES está temporariamente indisponível. Tente novamente dentro de alguns minutos.';
        }
        if (str_contains($combined, 'GLOBAL_MAX_CONCURRENT_REQ')) {
            return 'O serviço VIES está sob carga elevada. Aguarde um momento e volte a tentar.';
        }
        if (str_contains($combined, 'INVALID_INPUT')) {
            return 'Formato do número de IVA inválido para o país indicado.';
        }

        return $msg !== '' ? $msg : 'Erro ao consultar o VIES.';
    }
}
