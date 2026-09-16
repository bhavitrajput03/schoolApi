<?php
declare(strict_types=1);
namespace App\Core;

final class SqlServerConnectionString
{
    public static function pdo(string $connectionString): array
    {
        $values = self::parse($connectionString);
        $server = $values['server'] ?? $values['data source'] ?? null;
        $database = $values['database'] ?? $values['initial catalog'] ?? null;
        $username = $values['user id'] ?? $values['uid'] ?? $values['username'] ?? null;
        $password = $values['password'] ?? $values['pwd'] ?? null;

        if (!$server || !$database || !$username || $password === null) {
            throw new \DomainException(
                'SQL connection string requires Server, Database, User Id and Password.'
            );
        }

        $encrypt = self::boolean($values['encrypt'] ?? 'true');
        $trust = self::boolean($values['trustservercertificate'] ?? 'true');
        $dsn = sprintf(
            'sqlsrv:Server=%s;Database=%s;Encrypt=%d;TrustServerCertificate=%d',
            $server,
            $database,
            $encrypt ? 1 : 0,
            $trust ? 1 : 0
        );
        return [$dsn, $username, $password];
    }

    private static function parse(string $input): array
    {
        $parts = [];
        $buffer = '';
        $braced = false;
        $quoted = false;
        $characters = str_split(trim($input));
        for ($index = 0, $count = count($characters); $index < $count; $index++) {
            $character = $characters[$index];
            if ($character === '"' && !$braced) {
                // A doubled quote inside a quoted ODBC value represents one quote.
                if ($quoted && ($characters[$index + 1] ?? null) === '"') {
                    $buffer .= '""';
                    $index++;
                    continue;
                }
                $quoted = !$quoted;
            }
            if ($character === '{' && !$quoted) $braced = true;
            if ($character === '}' && !$quoted) $braced = false;
            if ($character === ';' && !$braced && !$quoted) {
                if (trim($buffer) !== '') $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $character;
        }
        if (trim($buffer) !== '') $parts[] = $buffer;

        $values = [];
        foreach ($parts as $part) {
            $pair = explode('=', $part, 2);
            if (count($pair) !== 2) {
                throw new \DomainException('Invalid SQL Server connection string.');
            }
            $key = strtolower(trim($pair[0]));
            $value = trim($pair[1]);
            if (str_starts_with($value, '{') && str_ends_with($value, '}')) {
                $value = substr($value, 1, -1);
            } elseif (str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = str_replace('""', '"', substr($value, 1, -1));
            }
            $values[$key] = $value;
        }
        return $values;
    }

    private static function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'mandatory'], true);
    }
}
