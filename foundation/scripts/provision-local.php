<?php

// Local development only. Uses only the isolated database instance created by start-local.ps1.
$local = dirname(__DIR__, 2).'/.local';
$credentialsFile = $local.'/mysql-credentials.json';
$fresh = ! is_file($credentialsFile);
$credentials = $fresh ? ['root' => bin2hex(random_bytes(32)), 'app' => bin2hex(random_bytes(32))] : json_decode(file_get_contents($credentialsFile), true, flags: JSON_THROW_ON_ERROR);
if ($fresh) {
    file_put_contents($credentialsFile, json_encode($credentials, JSON_THROW_ON_ERROR));
}
$pdo = null;
for ($attempt = 0; $attempt < 20; $attempt++) {
    try {
        try {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', $credentials['root'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException) {
            $pdo = new PDO('mysql:host=127.0.0.1;port=3307', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        }
        break;
    } catch (PDOException) {
        sleep(1);
    }
}
if (! $pdo) {
    fwrite(STDERR, "Cannot connect to the isolated local MySQL instance.\n");
    exit(1);
}
$actual = str_replace('\\', '/', $pdo->query('SELECT @@datadir')->fetchColumn());
$expected = str_replace('\\', '/', realpath($local.'/mysql'));
if (strtolower(rtrim($actual, '/')) !== strtolower(rtrim($expected, '/'))) {
    fwrite(STDERR, "Refusing to provision a database outside this workspace.\n");
    exit(1);
}
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($credentials['root']));
$pdo->exec('CREATE DATABASE IF NOT EXISTS tech_commerce CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec("CREATE USER IF NOT EXISTS 'tech_commerce'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($credentials['app']));
$pdo->exec("ALTER USER 'tech_commerce'@'127.0.0.1' IDENTIFIED BY ".$pdo->quote($credentials['app']));
$pdo->exec("GRANT ALL PRIVILEGES ON tech_commerce.* TO 'tech_commerce'@'127.0.0.1'");
$envPath = dirname(__DIR__).'/.env';
$env = file_get_contents(is_file($envPath) ? $envPath : dirname(__DIR__).'/.env.example');
$env = preg_replace('/^DB_PASSWORD=.*$/m', 'DB_PASSWORD='.$credentials['app'], $env);
$env = preg_replace('/^REDIS_PASSWORD=.*$/m', 'REDIS_PASSWORD='.trim(file_get_contents($local.'/redis-password')), $env);
file_put_contents($envPath, $env);
echo "Isolated local database and application credentials configured.\n";
