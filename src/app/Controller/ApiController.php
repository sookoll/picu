<?php

namespace App\Controller;

use App\Service\AlbumService;
use App\Service\Utilities;
use Psr\Container\ContainerInterface;

class ApiController extends BaseController
{
    public function __construct(ContainerInterface $container, protected readonly AlbumService $service)
    {
        parent::__construct($container);
        Utilities::ensureDirectoriesExists($this->settings);
        $this->service->initProviders($this->settings['providers'] ?? null, true);
    }
}
