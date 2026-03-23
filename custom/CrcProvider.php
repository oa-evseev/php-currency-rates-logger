<?php

require_once __DIR__ . '/../classes/AbstractCurrencyProvider.php';

class CrcProvider extends AbstractCurrencyProvider
{
    public function get_name(): string
    {
        return 'BCCR JSON CRC Provider';
    }

    public function get_rates(array $current_rates, string $target_date): array
    {
        $api_url = rtrim($this->settings['api_url'], '/');
        $token = $this->settings['api_token'];
        $indicator_buy = $this->settings['indicator_buy'];
        $indicator_sell = $this->settings['indicator_sell'];
        $language = $this->settings['language'] ?? 'ES';
        $timeout = $this->settings['timeout'] ?? 20;

        $date_for_api = date('Y/m/d', strtotime($target_date));

        $buy = $this->fetch_indicator(
            $api_url,
            $indicator_buy,
            $date_for_api,
            $language,
            $token,
            $timeout
        );

        $sell = $this->fetch_indicator(
            $api_url,
            $indicator_sell,
            $date_for_api,
            $language,
            $token,
            $timeout
        );

        if ($buy === null || $sell === null) {
            throw new Exception('CRC provider: cannot obtain buy/sell rate.');
        }

        $mid = ($buy + $sell) / 2.0;

        return [
            'CRC' => [
                'value' => $mid,
                'base_currency' => 'USD',
            ],
        ];
    }

    private function fetch_indicator(
        string $api_url,
        string $indicator,
        string $date,
        string $language,
        string $token,
        int $timeout
    ): ?float {

        $url =
            $api_url .
            '/indicadoresEconomicos/' . $indicator .
            '/series?fechaInicio=' . $date .
            '&fechaFin=' . $date .
            '&idioma=' . $language;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' =>
                    "Authorization: Bearer " . $token . "\r\n" .
                    "Accept: application/json\r\n",
                'timeout' => $timeout,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            return null;
        }

        $json = json_decode($response, true);

        if (
            !isset($json['estado']) ||
            !$json['estado'] ||
            empty($json['datos'][0]['series'])
        ) {
            return null;
        }

        $series = $json['datos'][0]['series'];

        $last = end($series);

        if (!isset($last['valorDatoPorPeriodo'])) {
            return null;
        }

        return (float) $last['valorDatoPorPeriodo'];
    }
}
