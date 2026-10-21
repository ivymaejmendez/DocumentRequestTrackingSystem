<?php
// Default credentials (for local XAMPP development)
$host    = '127.0.0.1';
$port    = '3306';
$db      = 'document_request_system';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

// 1. Read .env file directly without relying on putenv/getenv
// (InfinityFree and many shared hosts disable putenv() and getenv() for security)
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");

            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;

            if ($k === 'DB_HOST') $host = $v;
            elseif ($k === 'DB_PORT') $port = $v;
            elseif ($k === 'DB_NAME') $db = $v;
            elseif ($k === 'DB_USER') $user = $v;
            elseif ($k === 'DB_PASS') $pass = $v;
            elseif ($k === 'DB_CHARSET') $charset = $v;
        }
    }
}

// 2. Read from getenv if available (for GitHub Actions CI container)
if (function_exists('getenv')) {
    $envH = @getenv('DB_HOST');
    if ($envH) $host = $envH;
    $envP = @getenv('DB_PORT');
    if ($envP) $port = $envP;
    $envN = @getenv('DB_NAME');
    if ($envN) $db = $envN;
    $envU = @getenv('DB_USER');
    if ($envU) $user = $envU;
    $envPass = @getenv('DB_PASS');
    if ($envPass !== false && $envPass !== null) $pass = $envPass;
    $envC = @getenv('DB_CHARSET');
    if ($envC) $charset = $envC;
}

// 3. Optional local PHP override (e.g. config/database.local.php)
$localConfig = __DIR__ . '/database.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    $msg = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    if ($host === '127.0.0.1' || $host === 'localhost') {
        $msg .= '<br><br><strong>Note:</strong> Database host is currently set to <code>' . htmlspecialchars($host) . '</code>. On hosting providers like InfinityFree, MySQL runs on a dedicated server (e.g. <code>sqlXXX.infinityfree.com</code>). Please configure your database credentials in <code>.env</code> or <code>config/database.local.php</code>.';
    }
    exit($msg);
}
