<?php

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Monolog\Level;

return static function (ContainerBuilder $containerBuilder) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $rootPath = dirname(__DIR__);
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->load();
    $dotenv->required('ENVIRONMENT')->allowedValues(['development', 'production']);
    $dotenv->required('ADMIN_USER');
    $dotenv->required('ADMIN_PASS');

    $composerJson = json_decode(file_get_contents($rootPath . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);

    $env = $_ENV['ENVIRONMENT'];
    $basePath = $_ENV['BASE_PATH'] ?? '';
    $cacheDir = $rootPath . '/var/cache';
    $tokenDir = $rootPath . '/var/tokens';

    $settings = [
        'environment' => $env,
        'version' => $composerJson['version'],
        // Document root
        'documentRoot' => $docRoot,
        // Base path
        'basePath' => $basePath,
        // Root path
        'rootPath' => $rootPath,
        // Cache dir
        'cacheDir' => $cacheDir,
        // Tokens dir
        'tokenDir' => $tokenDir,
        // Route cache
        'route_cache' => $cacheDir . '/routes',
        // Admin user
        'admin_user' => $_ENV['ADMIN_USER'],
        'admin_pass' => $_ENV['ADMIN_PASS'],
        'api_token' => $_ENV['API_TOKEN'],
        // Database settings
        'db' => [
            'host' => $_ENV['DB_HOST'],
            'port' => $_ENV['DB_PORT'],
            'name' => $_ENV['DB_NAME'],
            'user' => $_ENV['DB_USER'],
            'pass' => $_ENV['DB_PASS'],
        ],
        'placeholder' => $_ENV['IMG_PLACEHOLDER'],
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
                    'SQ150' => 'q',
                    'S320' => 'n',
                    'M640' => 'z',
                    'M800' => 'c',
                    'L1024'=> 'l',
                    'L1600'=> 'h',
                    'L2048'=> 'k',
                ],
            ],
            // Local disk provider
            'disk' => [
                'enabled' => $_ENV['DISK_ENABLED'] === 'true',
                'editable' => true,
                // from documentRoot
                'importPath' => $_ENV['DISK_GALLERY_PATH'],
                // from basePath
                'cachePath' => $_ENV['DISK_CACHE_PATH'],
                'accept_image_file_types' => '/\.(gif|jpe?g|tif|png)$/i',
                'accept_video_file_types' => '/\.(mov|mpeg|mp4)$/i',
                'sizes' => [
                    'SQ150' => 150,
                    'S150' => 150,
                    'SQ320' => 320,
                    'S320' => 320,
                    'SQ640' => 640,
                    'M640' => 640,
                    'SQ800' => 800,
                    'M800' => 800,
                    'L1024'=> 1024,
                    'L1600'=> 1600,
                    'L2048'=> 2048,
                ],
                'allowLargerThumb' => $_ENV['DISK_ALLOW_LARGER_THUMBNAIL'] === 'true',
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
