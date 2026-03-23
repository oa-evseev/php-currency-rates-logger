<?php

class TemplateProvider extends AbstractCurrencyProvider
{
    public function get_name(): string
    {
        return 'Template custom provider';
    }

    public function get_rates(array $current_rates, string $target_date): array
    {
        /*
         * Available provider settings example:
         *
         * 'custom_providers' => [
         *     'TemplateProvider' => [
         *         'api_url' => 'https://example.com/api',
         *         'api_token' => 'YOUR_TOKEN',
         *         'timeout' => 20,
         *     ],
         * ],
         */

        /*
         * Available input:
         *
         * $current_rates:
         *     Current rates already loaded into the system
         *     in the internal base currency
         *     (normally EUR-based before final normalization).
         *
         * $target_date:
         *     Target rate date in format Y-m-d,
         *     usually taken from ECB data.
         */

        /*
         * Minimal example:
         *
         * return [
         *     'XXX' => 123.45,
         * ];
         *
         * This means:
         *     currency XXX has rate 123.45
         *     in the default system base currency
         *     from config['base_currency'].
         */

        /*
         * Extended example:
         *
         * return [
         *     'XXX' => [
         *         'value' => 123.45,
         *         'base_currency' => 'USD',
         *     ],
         * ];
         *
         * This means:
         *     currency XXX has rate 123.45 per 1 USD.
         *
         * The system will try to find USD in $current_rates
         * and convert the value into the internal base currency.
         */

        /*
         * Multiple currencies example:
         *
         * return [
         *     'XXX' => [
         *         'value' => 123.45,
         *         'base_currency' => 'USD',
         *     ],
         *     'YYY' => 456.78,
         * ];
         */

        throw new Exception('TemplateProvider is not implemented yet.');
    }
}

?>
