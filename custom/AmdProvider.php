<?php

class AmdProvider extends AbstractCurrencyProvider
{
    public function get_name(): string
    {
        return 'CBA SOAP AMD Provider';
    }

    public function get_rates(array $current_rates, string $target_date): array
    {
        $api_url = isset($this->settings['api_url'])
            ? trim($this->settings['api_url'])
            : '';

        $source_currency = isset($this->settings['source_currency'])
            ? strtoupper(trim($this->settings['source_currency']))
            : 'USD';

        $target_currency = isset($this->settings['target_currency'])
            ? strtoupper(trim($this->settings['target_currency']))
            : 'AMD';

        $timeout = isset($this->settings['timeout'])
            ? (int) $this->settings['timeout']
            : 20;

        if ($api_url === '') {
            throw new Exception('AMD provider: api_url is not configured.');
        }

        if ($source_currency === '') {
            throw new Exception('AMD provider: source_currency is not configured.');
        }

        if ($target_currency === '') {
            throw new Exception('AMD provider: target_currency is not configured.');
        }

        $rate_value = $this->fetch_rate($api_url, $target_date, $source_currency, $timeout);

        return [
            $target_currency => [
                'value' => $rate_value,
                'base_currency' => $source_currency,
            ],
        ];
    }

    private function fetch_rate(string $api_url, string $target_date, string $source_currency, int $timeout): float
    {
        $soap_body =
            '<?xml version="1.0" encoding="utf-8"?>' .
            '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" ' .
                           'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ' .
                           'xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">' .
                '<soap:Body>' .
                    '<ExchangeRatesByDateByISO xmlns="http://www.cba.am/">' .
                        '<date>' . htmlspecialchars($target_date, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</date>' .
                        '<ISO>' . htmlspecialchars($source_currency, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</ISO>' .
                    '</ExchangeRatesByDateByISO>' .
                '</soap:Body>' .
            '</soap:Envelope>';

        $context = stream_context_create(
            [
                'http' => [
                    'method' => 'POST',
                    'header' =>
                        "Content-Type: text/xml; charset=utf-8\r\n" .
                        "SOAPAction: \"http://www.cba.am/ExchangeRatesByDateByISO\"\r\n",
                    'content' => $soap_body,
                    'timeout' => $timeout,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]
        );

        $response = @file_get_contents($api_url, false, $context);

        if ($response === false) {
            throw new Exception('AMD provider: cannot load CBA response.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($response);

        if ($xml === false) {
            throw new Exception('AMD provider: cannot parse CBA XML response.');
        }

        $soap_namespace = 'http://schemas.xmlsoap.org/soap/envelope/';
        $cba_namespace = 'http://www.cba.am/';

        $body_nodes = $xml->children($soap_namespace);

        if (!isset($body_nodes->Body)) {
            throw new Exception('AMD provider: SOAP body is missing.');
        }

        $response_nodes = $body_nodes->Body->children($cba_namespace);

        if (!isset($response_nodes->ExchangeRatesByDateByISOResponse)) {
            throw new Exception('AMD provider: SOAP response node is missing.');
        }

        $result = $response_nodes
            ->ExchangeRatesByDateByISOResponse
            ->ExchangeRatesByDateByISOResult;

        if (!isset($result->Rates->ExchangeRate)) {
            throw new Exception('AMD provider: exchange rate node is missing.');
        }

        foreach ($result->Rates->ExchangeRate as $exchange_rate) {
            $iso = strtoupper(trim((string) $exchange_rate->ISO));

            if ($iso !== $source_currency) {
                continue;
            }

            $amount = (float) $exchange_rate->Amount;
            $rate = (float) $exchange_rate->Rate;

            if ($amount <= 0) {
                throw new Exception('AMD provider: invalid amount returned by CBA.');
            }

            if ($rate <= 0) {
                throw new Exception('AMD provider: invalid rate returned by CBA.');
            }

            return $rate / $amount;
        }

        throw new Exception('AMD provider: requested ISO was not found in CBA response.');
    }
}

?>
