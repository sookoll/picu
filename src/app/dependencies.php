<?php

use App\Middleware\SessionMiddleware;
use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Slim\Views\Twig;

return static function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        'logger' => function (ContainerInterface $container) {
            $settings = $container->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },
        'session' => function (ContainerInterface $container) {
            return new SessionMiddleware;
        },
        'view' => function (ContainerInterface $container) {
            $settings = $container->get('settings');
            return Twig::create($settings['view']['template_path'], $settings['view']['twig']);
        },
        'db' => function (ContainerInterface $container) {
            $settings = $container->get('settings');
            $dbSettings = $settings['db'];

            $dsn = "mysql:host={$dbSettings['host']};port={$dbSettings['port']};dbname={$dbSettings['name']};charset=utf8";
            $database = new PDO($dsn, $dbSettings['user'], $dbSettings['pass']);
            $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            return $database;
        }
    ]);
};
