<?php
/*
 * Picu
 * @sookoll
 * 2024-05
 */

use DI\ContainerBuilder;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

// Instantiate PHP-DI ContainerBuilder
$containerBuilder = new ContainerBuilder();

// Set up settings
$settings = require __DIR__ . '/conf/settings.php';
$config = $settings($containerBuilder);

if ($config['environment'] !== 'development') {
    $containerBuilder->enableCompilation($config['cacheDir']);
}

// Set up dependencies
$dependencies = require __DIR__ . '/app/dependencies.php';
$dependencies($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Instantiate the app
$app = AppFactory::createFromContainer($container);
$app->setBasePath($config['base_path']);

// Register middleware
$middleware = require __DIR__ . '/app/middleware.php';
$middleware($app);

// Register routes
$routes = require __DIR__ . '/app/routes.php';
$routes($app);

// Set the cache file for the routes. Note that you have to delete this file
// whenever you change the routes.
if ($config['environment'] !== 'development') {
    $app->getRouteCollector()->setCacheFile($config['route_cache']);
}

// Add the routing middleware.
$app->addRoutingMiddleware();

// Add error handling middleware.
//if (!$config['environment'] !== 'development') {
    $errorMiddleware = $app->addErrorMiddleware(true, true, true);
    $errorHandler = $errorMiddleware->getDefaultErrorHandler();
    $errorHandler->registerErrorRenderer('text/html', App\Renderer\HtmlErrorRenderer::class);
//}

// Run the app
$app->run();
