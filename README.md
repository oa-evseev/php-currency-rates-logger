# PHP Currency Rates Logger

Lightweight standalone PHP utility for collecting and storing historical currency exchange rates from multiple providers.

The utility is designed for simple daily ingestion of reference rates into a database and supports extension via custom providers.

---

## Quick start

```bash
git clone https://github.com/oa-evseev/php-currency-rates-logger.git
cd php-currency-rates-logger
cp config.example.php config.php
# edit config.php and set your database credentials and provider settings
php run.php
```

---

## Architecture Overview

The rate update pipeline works as follows:

1. Load daily reference rates from the ECB provider.
2. Apply currency filtering (blacklist or whitelist).
3. Run custom providers to add or override currencies.
4. Normalize all rates to the configured primary currency.
5. Store rates in the historical database table.
6. Update the last processed date marker.
7. Protect execution using a filesystem lock to prevent parallel runs.

---

## Features

- Fetches daily reference rates from the European Central Bank (ECB)
- Supports additional custom currency providers
- Stores rates historically (time-series logging)
- Provider architecture allows easy API integrations
- Configurable currency filtering (whitelist / blacklist)
- CLI execution (cron-friendly)
- Minimal web GUI for manual execution
- File locking to prevent parallel runs
- Planned sanity-check framework for abnormal rate detection

---

## Requirements

- PHP 7.2+
- PDO extension
- MySQL (or compatible database)
- Cron (recommended for automation)

---

## Installation

Clone the repository:

```
git clone https://github.com/<your_user>/php-currency-rates-logger.git
```

Enter the project directory:

```
cd php-currency-rates-logger
```

Create configuration file:

```
cp config.example.php config.php
```

Then edit:

```
config.php
```

---

## Configuration

### Database

Configure database connection via PDO:

```
'db' => [
    'dsn' => 'mysql:host=localhost;dbname=database;charset=utf8',
    'username' => 'username',
    'password' => 'password',
]
```

The utility expects two tables:

### currencies

Stores historical currency rates.

Example structure:

```
code   VARCHAR
value  DECIMAL
date   DATE
```

### current

Stores last processed rate date marker.

---

### Base and Primary Currency

```
'base_currency' => 'EUR',
'primary_currency' => 'GBP',
```

Internal workflow:

- Providers normally return rates relative to **base_currency**
- Final stored values are normalized to **primary_currency**

---

### ECB Provider Settings

```
'ecb_url' => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
```

Currency filtering:

```
'ecb_currency_filter_mode' => 'blacklist',
'ecb_currency_filter_list' => [],
```

Modes:

- `blacklist` → exclude listed currencies
- `whitelist` → include only listed currencies

---

### Custom Providers

Custom providers allow integrating additional currency sources.

Example:

```
'custom_providers' => [
    'CrcProvider' => [
        'api_url' => '...',
        'api_token' => 'YOUR_TOKEN',
    ],
]
```

Each provider receives its own settings block.

Custom providers run **after ECB rates are loaded and filtered**.

They may:

- add missing currencies
- override ECB currencies
- return rates relative to another currency

---

### Sanity Checks (In Development)

```
'sanity_checks' => [
    'CRC' => [
        'max_relative_change' => 0.3,
    ],
]
```

Sanity‑check validation is planned to detect abnormal rate jumps before storing.

---

## Running the Utility

### CLI

```
php run.php
```

---

### Cron Example

```
0 10 * * * /usr/local/bin/php /path/to/php-currency-rates-logger/run.php
```

Optional logging:

```
...run.php >> /path/to/logfile.log 2>&1
```

---

### Web GUI

Open in browser:

```
index.php
```

Allows manual execution and shows execution report.

---

## Provider Architecture

To add a new provider:

1. Create file in:

```
custom/
```

2. Extend:

```
AbstractCurrencyProvider
```

3. Implement:

```
get_rates()
```

Use `TemplateProvider.php` as reference implementation.

---

## Example Provider Return Format

Minimal:

```
return [
    'XXX' => 123.45,
];
```

Extended:

```
return [
    'XXX' => [
        'value' => 123.45,
        'base_currency' => 'USD',
    ],
];
```

---

## Included Providers

- EcbProvider — primary reference source
- CrcProvider — Costa Rica Central Bank JSON API
- AmdProvider — Armenia Central Bank SOAP API
- TemplateProvider — starter template

---

## Notes

- The utility is intended for daily reference rates, not trading data.
- Historical records are append‑only.
- Lock file prevents concurrent runs.
