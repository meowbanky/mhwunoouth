<?php
/**
 * OOUTH Salary API Configuration — reads from .env
 */

// Load .env if not already loaded by hms.php
if (!isset($_ENV['OOUTH_API_KEY'])) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            [$name, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && $value[0] === '"' && $value[-1] === '"') $value = substr($value, 1, -1);
            elseif (strlen($value) >= 2 && $value[0] === "'" && $value[-1] === "'") $value = substr($value, 1, -1);
            $_ENV[trim($name)] = $value;
        }
    }
}

define('OOUTH_API_BASE_URL',    $_ENV['OOUTH_API_BASE_URL']    ?? 'https://oouthsalary.com.ng/api/v1');
define('OOUTH_API_KEY',         $_ENV['OOUTH_API_KEY']         ?? '');
define('OOUTH_API_SECRET',      $_ENV['OOUTH_API_SECRET']      ?? '');
define('OOUTH_ORGANIZATION_ID', $_ENV['OOUTH_ORGANIZATION_ID'] ?? '');
define('OOUTH_RESOURCE_TYPE',   $_ENV['OOUTH_RESOURCE_TYPE']   ?? 'deduction');
define('OOUTH_RESOURCE_ID',     $_ENV['OOUTH_RESOURCE_ID']     ?? '');
define('OOUTH_RESOURCE_NAME',   $_ENV['OOUTH_RESOURCE_NAME']   ?? '');
define('OOUTH_API_TIMEOUT',     (int)($_ENV['OOUTH_API_TIMEOUT'] ?? 30));
define('OOUTH_API_DEBUG',       ($_ENV['OOUTH_API_DEBUG']      ?? 'false') === 'true');
