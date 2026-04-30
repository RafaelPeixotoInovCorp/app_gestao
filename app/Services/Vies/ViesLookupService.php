<?php

namespace App\Services\Vies;

use SoapFault;

class ViesLookupService
{
    private const WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /**
     * @return array{valid: bool, name: ?string, address: ?string, request_date: ?string, fault: ?string}
     */
    public function check(string $countryCode, string $vatNumber): array
    {
        $countryCode = strtoupper(preg_replace('/\s+/', '', $countryCode));
        $vatNumber = preg_replace('/\s+/', '', $vatNumber);

        if (strlen($countryCode) !== 2 || $vatNumber === '') {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'Código de país ou NIF inválido.',
            ];
        }

        if (! extension_loaded('soap')) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'A extensão PHP SOAP não está disponível no servidor.',
            ];
        }

        try {
            $client = new \SoapClient(self::WSDL, [
                'connection_timeout' => 10,
                'cache_wsdl' => WSDL_CACHE_DISK,
            ]);

            $result = $client->checkVat([
                'countryCode' => $countryCode,
                'vatNumber' => $vatNumber,
            ]);

            $valid = (bool) ($result->valid ?? false);

            return [
                'valid' => $valid,
                'name' => $valid ? ($result->name ?? null) : null,
                'address' => $valid ? ($result->address ?? null) : null,
                'request_date' => $result->requestDate ?? null,
                'fault' => $valid ? null : 'NIF não encontrado no VIES.',
            ];
        } catch (SoapFault $e) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => $e->getMessage(),
            ];
        } catch (\Throwable) {
            return [
                'valid' => false,
                'name' => null,
                'address' => null,
                'request_date' => null,
                'fault' => 'Erro ao contactar o serviço VIES.',
            ];
        }
    }
}
