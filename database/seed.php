<?php
// Rebuilds the database from schema.sql, then loads base data and, with --demo, sample activity.
// Usage: php database/seed.php [--demo] [--database=name]
// This erases the target database, and refuses to run if APP_ENV=production.
if (PHP_SAPI !== 'cli') {
    exit('Run this from the command line.');
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/seeders/BaseSeeder.php';
require __DIR__ . '/seeders/DemoSeeder.php';

use App\Core\Database as DB;

$demo = in_array('--demo', $argv, true);
foreach ($argv as $a) {
    if (str_starts_with($a, '--database=')) {
        $_ENV['DB_DATABASE'] = substr($a, 11);
    }
}
if (config('app.env') === 'production') {
    exit("Refusing to run: APP_ENV is production.\n");
}
$c = config('database');
if (!preg_match('/^[A-Za-z0-9_]+$/', $c['database'])) {
    exit("Invalid database name.\n");
}
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("DROP DATABASE IF EXISTS `{$c['database']}`");
$pdo->exec("CREATE DATABASE `{$c['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$c['database']}`");
$sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/schema.sql'));
foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
    $pdo->exec($stmt);
}

$adminId = BaseSeeder::run();
echo "Base data loaded.\n";
if ($demo) {
    (new DemoSeeder())->run($adminId);
    echo "Demo data loaded.\n";
}
foreach (['bird_transactions', 'feed_transactions', 'egg_transactions', 'financial_transactions', 'sales', 'purchases', 'payments'] as $t) {
    echo str_pad($t, 26) . DB::value("SELECT COUNT(*) FROM `$t`") . "\n";
}
