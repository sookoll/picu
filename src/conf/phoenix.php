<?php

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);
// $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv = Dotenv::createImmutable($rootPath);
$dotenv->load();
$dotenv->required('ENVIRONMENT')->allowedValues(['development', 'production']);
$dotenv->required('ADMIN_USER');
$dotenv->required('ADMIN_PASS');

echo $_ENV['ENVIRONMENT'];

return [
    'migration_dirs' => [
        'db' => $rootPath . '/db/migrations',
    ],
    'environments' => [
        'development' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS'],
            'db_name' => $_ENV['DB_NAME'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci', // optional, if not set default collation for utf8mb4 is used
        ],
        'production' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'username' => $_ENV['DB_USER'],
            'password' => $_ENV['DB_PASS'],
            'db_name' => $_ENV['DB_NAME'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci', // optional, if not set default collation for utf8mb4 is used
        ],
    ],
    'default_environment' => $_ENV['ENVIRONMENT'],
    'log_table_name' => 'migrations',
];
