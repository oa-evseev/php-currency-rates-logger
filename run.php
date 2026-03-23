<?php

require_once(__DIR__ . '/db.php');
require_once(__DIR__ . '/classes/AbstractCurrencyProvider.php');
require_once(__DIR__ . '/classes/EcbProvider.php');


function acquire_lock_or_exit(string $lock_name)
{
    $lock_file = sys_get_temp_dir() . '/' . $lock_name . '.lock';

    $fp = fopen($lock_file, 'c');

    if ($fp === false) {
        throw new Exception('Cannot create lock file.');
    }

    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        return false;
    }

    return $fp;
}

function load_custom_provider(string $class_name, array $settings = array()): AbstractCurrencyProvider
{
    $file_path = __DIR__ . '/custom/' . $class_name . '.php';

    if (!is_file($file_path)) {
        throw new Exception('Custom provider file not found: ' . $class_name);
    }

    require_once($file_path);

    if (!class_exists($class_name)) {
        throw new Exception('Custom provider class not found: ' . $class_name);
    }

    $provider = new $class_name($settings);

    if (!($provider instanceof AbstractCurrencyProvider)) {
        throw new Exception('Custom provider must extend AbstractCurrencyProvider: ' . $class_name);
    }

    return $provider;
}

function get_last_currency_date(PDO $pdo): ?string
{
    $stmt = $pdo->prepare("SELECT `number` FROM `current` WHERE `journal` = :journal LIMIT 1");
    $stmt->execute(
        [
            ':journal' => 'd',
        ]
    );

    $value = $stmt->fetchColumn();

    if ($value === false) {
        return null;
    }

    return (string) $value;
}

function set_last_currency_date(PDO $pdo, string $date): void
{
    $stmt = $pdo->prepare("UPDATE `current` SET `number` = :date WHERE `journal` = :journal");
    $stmt->execute(
        [
            ':date' => $date,
            ':journal' => 'd',
        ]
    );
}

function filter_ecb_rates(array $rates, string $filter_mode, array $filter_list): array
{
    if ($filter_mode !== 'blacklist' && $filter_mode !== 'whitelist') {
        throw new Exception('Unsupported ECB currency filter mode: ' . $filter_mode);
    }

    if (empty($filter_list)) {
        return $rates;
    }

    $filter_map = array_flip($filter_list);
    $filtered_rates = [];

    if ($filter_mode === 'blacklist') {
        foreach ($rates as $currency_code => $rate) {
            if (!isset($filter_map[$currency_code])) {
                $filtered_rates[$currency_code] = $rate;
            }
        }

        return $filtered_rates;
    }

    foreach ($rates as $currency_code => $rate) {
        if (isset($filter_map[$currency_code])) {
            $filtered_rates[$currency_code] = $rate;
        }
    }

    return $filtered_rates;
}

function normalize_rates_to_primary(array $rates, string $primary_currency): array
{
    if (!isset($rates[$primary_currency])) {
        throw new Exception('Primary currency is missing: ' . $primary_currency);
    }

    $primary_rate = (float) $rates[$primary_currency];

    if ($primary_rate <= 0) {
        throw new Exception('Primary currency rate must be positive.');
    }

    $normalized_rates = [];

    foreach ($rates as $currency_code => $rate) {
        $normalized_rates[$currency_code] = (float) $rate / $primary_rate;
    }

    return $normalized_rates;
}

function save_currency_rates(PDO $pdo, array $rates, string $date): int
{
    $stmt = $pdo->prepare(
        "INSERT INTO `currencies` (`code`, `value`, `date`) VALUES (:code, :value, :date)"
    );

    $count = 0;

    foreach ($rates as $currency_code => $rate) {
        $stmt->execute(
            [
                ':code' => $currency_code,
                ':value' => $rate,
                ':date' => $date,
            ]
        );

        $count++;
    }

    return $count;
}

function build_success_mail_text(string $date): string
{
    return "Done!\n\nThe rates are up to date (" . date('F j, Y', strtotime($date)) . ").";
}

function normalize_provider_rates_to_base_currency(array $provider_rates, array $current_rates, string $default_base_currency): array
{
    $normalized_rates = [];

    foreach ($provider_rates as $currency_code => $rate_definition) {
        if (is_array($rate_definition)) {
            if (!array_key_exists('value', $rate_definition)) {
                throw new Exception('Provider rate definition must contain "value" for currency: ' . $currency_code);
            }

            $value = (float) $rate_definition['value'];
            $base_currency = isset($rate_definition['base_currency'])
                ? (string) $rate_definition['base_currency']
                : $default_base_currency;
        } else {
            $value = (float) $rate_definition;
            $base_currency = $default_base_currency;
        }

        if ($value <= 0) {
            throw new Exception('Provider rate must be positive for currency: ' . $currency_code);
        }

        if ($base_currency === $default_base_currency) {
            $normalized_rates[$currency_code] = $value;
            continue;
        }

        if (!isset($current_rates[$base_currency])) {
            throw new Exception(
                'Cross-rate base currency not found for ' . $currency_code . ': ' . $base_currency
            );
        }

        $cross_rate = (float) $current_rates[$base_currency];

        if ($cross_rate <= 0) {
            throw new Exception(
                'Cross-rate base currency must be positive for ' . $currency_code . ': ' . $base_currency
            );
        }

        $normalized_rates[$currency_code] = $value * $cross_rate;
    }

    return $normalized_rates;
}

function run_currency_update(bool $send_mail = true): array
{
    $config = require __DIR__ . '/config.php';
    $lock = acquire_lock_or_exit('php_currency_rates_logger');

    if ($lock === false) {
        return [
            'success' => false,
            'status' => 'locked',
            'message' => 'Another update process is already running.',
        ];
    }

    try {
        $pdo = get_pdo($config['db']);

        $ecb_provider = new EcbProvider(
            $config['ecb_url'],
            $config['base_currency']
        );

        $ecb_data = $ecb_provider->get_data();
        $date = $ecb_data['date'];
        $rates = $ecb_data['rates'];

        $last_date_in_db = get_last_currency_date($pdo);

        if ($last_date_in_db === $date) {
            return [
                'success' => true,
                'status' => 'up_to_date',
                'message' => 'Rates are already up to date.',
                'date' => $date,
                'last_date_in_db' => $last_date_in_db,
                'providers' => [],
            ];
        }

        $rates = filter_ecb_rates(
            $rates,
            $config['ecb_currency_filter_mode'],
            $config['ecb_currency_filter_list']
        );

        $loaded_providers = [];

        foreach ($config['custom_providers'] as $class_name => $provider_settings) {
            $provider = load_custom_provider($class_name, $provider_settings);
            $patch_rates = $provider->get_rates($rates, $date);

            $normalized_patch_rates = normalize_provider_rates_to_base_currency(
                $patch_rates,
                $rates,
                $config['base_currency']
            );

            foreach ($normalized_patch_rates as $currency_code => $rate) {
                $rates[$currency_code] = $rate;
            }

            $loaded_providers[] = $provider->get_name();
        }

        // TODO: optional sanity check for custom providers
        // idea: compare new rate with previous DB value
        // if relative change exceeds configured threshold → throw exception

        $normalized_rates = normalize_rates_to_primary(
            $rates,
            $config['primary_currency']
        );

        $pdo->beginTransaction();

        $saved_count = save_currency_rates($pdo, $normalized_rates, $date);
        set_last_currency_date($pdo, $date);

        $pdo->commit();

        $mail_sent = false;
        $mail_recipients = implode(',', $config['notification_emails']);

        if ($send_mail && $mail_recipients !== '') {
            $mail_headers = "From: webmaster@lasphys.com\r\nContent-type: text/plain; charset=utf-8";
            $mail_subject = 'Currencies updated';
            $mail_text = build_success_mail_text($date);

            $mail_sent = mail($mail_recipients, $mail_subject, $mail_text, $mail_headers);
        }

        return [
            'success' => true,
            'status' => 'updated',
            'message' => 'Currency rates were updated successfully.',
            'date' => $date,
            'last_date_in_db' => $last_date_in_db,
            'saved_count' => $saved_count,
            'providers' => $loaded_providers,
            'tracked_currencies' => array_keys($normalized_rates),
            'mail_sent' => $mail_sent,
        ];
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [
            'success' => false,
            'status' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
}

if (PHP_SAPI === 'cli') {
    $result = run_currency_update(true);

    if ($result['success']) {
        echo '[OK] ' . $result['message'] . PHP_EOL;

        if (isset($result['date'])) {
            echo 'Date: ' . $result['date'] . PHP_EOL;
        }

        exit(0);
    }

    fwrite(STDERR, '[ERROR] ' . $result['message'] . PHP_EOL);
    exit(1);
}

?>
