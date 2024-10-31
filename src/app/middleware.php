<?php

use App\Middleware\BaseUrlMiddleware;
use App\Model\Photo;
use App\Model\PhotoSize;
use Slim\App;
use Slim\Views\TwigMiddleware;
use Twig\TwigFunction;

return static function (App $app) {
    $container = $app->getContainer();
    $settings = $container?->get('settings');

    $app->add($container?->get('session'));
    $app->add(TwigMiddleware::createFromContainer($app));
    $app->add(new BaseUrlMiddleware($app->getBasePath()));

    /** Twig extensions */
    $imgPlaceholder = new TwigFunction('placeholder', function ($width = 150, $height = 150, $title = '') use ($settings) {
        $bg = '111';
        $font = '222';
        if ($title === '') {
            $font = $bg;
        }
        return sprintf($settings['placeholder'], $width, $height, $bg, $font, $title);
    });

    $container?->get('view')->getEnvironment()->addFunction($imgPlaceholder);
};
