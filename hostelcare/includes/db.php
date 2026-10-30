<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (cfg('db.driver') === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', cfg('db.host'), cfg('db.port'), cfg('db.name'));
        $pdo = new PDO($dsn, (string) cfg('db.user'), (string) cfg('db.pass'), $options);
    } else {
        $path = (string) cfg('db.sqlite_path');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = [])
{
    $value = q($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function db_installed(): bool
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function db_install_schema(): void
{
    $file = HC_ROOT . '/database/schema.' . (cfg('db.driver') === 'mysql' ? 'mysql' : 'sqlite') . '.sql';
    $sql = preg_replace('/^--.*$/m', '', (string) file_get_contents($file));
    foreach (preg_split('/;\s*\n/', (string) $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            db()->exec($statement);
        }
    }
}

function setting(string $key, string $default = ''): string
{
    $value = q_val('SELECT v FROM settings WHERE k = ?', [$key]);
    return $value === null ? $default : (string) $value;
}

function set_setting(string $key, string $value): void
{
    if (q_val('SELECT 1 FROM settings WHERE k = ?', [$key]) !== null) {
        q('UPDATE settings SET v = ? WHERE k = ?', [$value, $key]);
    } else {
        q('INSERT INTO settings (k, v) VALUES (?, ?)', [$key, $value]);
    }
}
