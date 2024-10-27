<?php

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Monolog\Level;

return static function (ContainerBuilder $containerBuilder) {
    $rootPath = dirname(__DIR__);
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->load();
    $dotenv->required('ENVIRONMENT')->allowedValues(['development', 'production']);
    $dotenv->required('ADMIN_USER');
    $dotenv->required('ADMIN_PASS');

    $env = $_ENV['ENVIRONMENT'];
    $cacheDir = $rootPath . '/var/cache';
    $tokenDir = $rootPath . '/var/tokens';

    $settings = [
        'environment' => $env,
        // Base path
        'base_path' => $_ENV['BASE_PATH'] ?? '',
        // Admin user
        'admin_user' => $_ENV['ADMIN_USER'],
        'admin_pass' => $_ENV['ADMIN_PASS'],
        'api_token' => $_ENV['API_TOKEN'],
        // Root path
        'rootPath' => $rootPath,
        // Cache dir
        'cacheDir' => $cacheDir,
        // Tokens dir
        'tokenDir' => $tokenDir,
        // Route cache
        'route_cache' => $cacheDir . '/routes',
        // Database settings
        'db' => [
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS'],
        ],
        // View settings
        'view' => [
            'template_path' => $rootPath . '/ui/tmpl',
            'twig' => [
                'cache' => $cacheDir . '/twig',
                'debug' => $env === 'development',
                'auto_reload' => true,
            ],
        ],
        // monolog settings
        'logger' => [
            'name' => 'picu',
            'path' =>  $rootPath . '/var/log/picu.log',
            'level' => $env === 'development' ? Level::Debug : Level::Info,
        ],
        // Picture providers settings
        'providers' => [
            // Flickr provider
            'flickr' => [
                'enabled' => $_ENV['FLICKR_ENABLED'] === 'true',
                'token_file' => $tokenDir . '/flickr.json',
                'key' => $_ENV['FLICKR_KEY'],
                'secret' => $_ENV['FLICKR_SECRET'],
                'perms' => $_ENV['FLICKR_PERMISSION'],
                'sizes' => ['thumb'=>'Thumbnail','small'=>'Small 320','medium'=>'Medium 800','large'=>'Large 1600'],
                'th_size' => 300,
                'vb_size' => 'k',
            ],
            // Local disk provider
            'disk' => [
                'enabled' => $_ENV['DISK_ENABLED'] === 'true',
                'editable' => true,
                'root_path' => $rootPath,
                'import_path' => $_ENV['DISK_GALLERY_PATH'],
                'cache_path' => $_ENV['DISK_CACHE_PATH'],
                'accept_image_file_types' => '/\.(gif|jpe?g|tif|png)$/i',
                'accept_video_file_types' => '/\.(mov|mpeg|mp4)$/i',
                'sizes' => ['thumb'=>'Thumbnail','small'=>'Small 320','medium'=>'Medium 800','large'=>'Large 1600'],
                'th_size' => 300,
                'vb_size' => 'k',
            ]
        ],
        // upload
        'upload' => [
            // allow anon upload (used for uploading from gf-client to flickr)
            'anon' => true,
            'accept_image_file_types' => '/\.(gif|jpe?g|tif|png)$/i',
            'accept_video_file_types' => '/\.(mov|mpeg|mp4)$/i',
            'max_file_size' => 7000000
        ],
        'download' => [
            'referer' => $_ENV['CURL_REFERER'],
        ]
    ];

    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => $settings
    ]);

    return $settings;
};
