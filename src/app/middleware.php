<?php

use App\Middleware\BaseUrlMiddleware;
use Slim\App;
use Slim\Views\TwigMiddleware;

return static function (App $app) {
    $container = $app->getContainer();

    $app->add($container?->get('session'));
    $app->add(TwigMiddleware::createFromContainer($app));
    $app->add(new BaseUrlMiddleware($app->getBasePath()));
};
