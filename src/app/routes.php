<?php
declare(strict_types=1);

use App\Controller\AdminController;
use App\Controller\ApiController;
use App\Controller\AuthController;
use App\Controller\GalleryController;
use App\Controller\LegacyGalleryController;
use App\Controller\HomeController;
use App\Controller\ImageController;
use App\Middleware\AuthenticationMiddleware;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {
    $container = $app->getContainer();
    $settings = $container?->get('settings');

    // index
    $app->get('/', HomeController::class . ':index')->setName('root');
    // Admin
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
        $group->get('/{provider}/import', AdminController::class . ':validate')
            ->setName('import_validate')
            ->add(AuthenticationMiddleware::class);
        $group->get('/{provider}/sync/{album}', AdminController::class . ':import')
            ->setName('import_sync')
            ->add(AuthenticationMiddleware::class);
        $group->post('/{provider}/upload[/{album}]', AdminController::class . ':upload')
            ->setName('provider_upload')
            ->add(AuthenticationMiddleware::class);
        $group->get('/{provider}/{album}', AdminController::class . ':album')
            ->setName('provider_album')
            ->add(AuthenticationMiddleware::class);
        $group->put('/{provider}/{album}', AdminController::class . ':updateAlbum')
            ->setName('provider_album')
            ->add(AuthenticationMiddleware::class);
        $group->put('/{provider}/{album}/{item}', AdminController::class . ':updateItem')
            ->setName('provider_item')
            ->add(AuthenticationMiddleware::class);
        $group->delete('/{provider}/{album}', AdminController::class . ':delete')
            ->setName('album_delete')
            ->add(AuthenticationMiddleware::class);
    });
    // API
    $app->group('/api', function (Group $group) {
        $group->get('/set[/{album}]', ApiController::class . ':set')
            ->setName('api_set');
        $group->get('/item/sizes', ApiController::class . ':sizes')
            ->setName('api_item_sizes');
        $group->get('/item/{album}[/{item}]', ApiController::class . ':item')
            ->setName('api_item');
    });
    // Image
    $app->get('/media/cache/{album}/{item}_{size}.{ext}', ImageController::class . ':image')->setName('api_image');

    // Deprecated: Legacy gallery endpoints
    $app->get('/a/{album}[/{photo}]', LegacyGalleryController::class . ':album');
    $app->get('/p/{album}/{photo}', LegacyGalleryController::class . ':photo');
    $app->get('/d/{album}/{photo}', LegacyGalleryController::class . ':download');

    // Gallery endpoints
    $app->get('/{album}_{item}', GalleryController::class . ':photo')->setName('item');
    $app->get('/{album}_{item}?download', GalleryController::class . ':photo')->setName('download');
    $app->get('/{album}[/{item}]', GalleryController::class . ':album')->setName('album');
};
