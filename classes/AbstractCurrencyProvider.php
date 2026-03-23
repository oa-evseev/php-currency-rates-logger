<?php

abstract class AbstractCurrencyProvider
{
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    abstract public function get_name(): string;

    abstract public function get_rates(array $current_rates, string $target_date): array;
}

?>
