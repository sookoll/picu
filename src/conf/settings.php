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
                'sizes' => [
                    'sq150' => 'q',
                    's320' => 'n',
                    'm640' => 'z',
                    'm800' => 'c',
                    'l1024'=> 'l',
                    'l1600'=> 'h',
                    'l2048'=> 'k',
                ],
                'th_size' => 300,
                'vb_size' => 'k',
                'import_max_count' => (int)$_ENV['FLICKR_IMPORT_MAX'],
            ],
            // Local disk provider
            'disk' => [
                'enabled' => $_ENV['DISK_ENABLED'] === 'true',
                'editable' => true,
                'import_path' => $_ENV['DISK_GALLERY_PATH'],
                'cache_path' => $_ENV['DISK_CACHE_PATH'],
                'accept_image_file_types' => '/\.(gif|jpe?g|tif|png)$/i',
                'accept_video_file_types' => '/\.(mov|mpeg|mp4)$/i',
                'sizes' => [
                    'sq150' => 150,
                    's320' => 320,
                    'm640' => 640,
                    'm800' => 800,
                    'l1024'=> 1024,
                    'l1600'=> 1600,
                    'l2048'=> 2048,
                ],
                'th_size' => 300,
                'vb_size' => 'l2048',
                'import_max_count' => (int)$_ENV['DISK_IMPORT_MAX'],
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
        ],
    ];

    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => $settings
    ]);

    return $settings;
};
