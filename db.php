<?php

function get_pdo(array $db_config): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $options = isset($db_config['options']) ? $db_config['options'] : [];

    $pdo = new PDO(
        $db_config['dsn'],
        $db_config['username'],
        $db_config['password'],
        $options
    );

    return $pdo;
}

?>
