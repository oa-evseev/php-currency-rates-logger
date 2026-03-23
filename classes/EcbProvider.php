<?php

class EcbProvider
{
    private $xml_url;
    private $base_currency;

    public function __construct(string $xml_url, string $base_currency = 'EUR')
    {
        $this->xml_url = $xml_url;
        $this->base_currency = $base_currency;
    }

    public function get_data(): array
    {
        $context = stream_context_create(
            [
                'http' => [
                    'timeout' => 20,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]
        );

        $xml_text = @file_get_contents($this->xml_url, false, $context);

        if ($xml_text === false) {
            throw new Exception('Cannot load ECB currency data.');
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_text);

        if ($xml === false) {
            throw new Exception('Cannot parse ECB currency XML.');
        }

        $cube_root = $xml->Cube->Cube;

        if (!isset($cube_root['time'])) {
            throw new Exception('ECB XML does not contain rate date.');
        }

        $date = (string) $cube_root['time'];
        $rates = [];

        foreach ($cube_root->Cube as $currency_node) {
            $currency_code = (string) $currency_node['currency'];
            $currency_rate = (float) $currency_node['rate'];

            if ($currency_code !== '') {
                $rates[$currency_code] = $currency_rate;
            }
        }

        $rates[$this->base_currency] = 1.0;

        return [
            'date' => $date,
            'rates' => $rates,
        ];
    }
}

?>
