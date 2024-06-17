<?php
declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\GalleryController;
use App\Controller\HomeController;
use App\Middleware\AuthenticationMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $container = $app->getContainer();
    $settings = $container?->get('settings');

    $app->get('/', HomeController::class . ':index')->setName('root');
    $app->group('/admin', function (Group $group) {
        $group->map(['GET', 'POST'],'/login', AuthController::class . ':login')->setName('login');
        $group->get('/logout', AuthController::class . ':logout')->setName('logout');
        $group->get('/hash/{pwd}', AuthController::class . ':hash')->setName('hash');
        $group->get('', AdminController::class . ':index')
            ->setName('admin')
            ->add(AuthenticationMiddleware::class);
        $group->get('/{provider}/login', AdminController::class . ':login')
            ->setName('provider_login')
            ->add(AuthenticationMiddleware::class);
        $group->get('/{provider}/logout', AdminController::class . ':logout')
            ->setName('provider_logout')
            ->add(AuthenticationMiddleware::class);
        $group->get('/{provider}/remove-cache[/{album}]', AdminController::class . ':removeCache')
            ->setName('provider_remove_cache')
            ->add(AuthenticationMiddleware::class);
        $group->post('/{provider}/upload[/{album}]', AdminController::class . ':upload')
            ->setName('provider_upload')
            ->add(AuthenticationMiddleware::class);
    });
    $app->get('/a/{album}[/{photo}]', GalleryController::class . ':album')->setName('album');
    $app->get('/p/{album}/{photo}', GalleryController::class . ':photo')->setName('photo');
    $app->get('/d/{album}/{photo}', GalleryController::class . ':download')->setName('photo_download');
};