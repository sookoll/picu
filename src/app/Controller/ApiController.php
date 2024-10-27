<?php

namespace App\Controller;

use App\Enum\ProviderEnum;
use App\Service\AlbumService;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController extends BaseController
{
    public function __construct(ContainerInterface $container, protected readonly AlbumService $service)
    {
        parent::__construct($container);
    }

    /**
     * @throws JsonException
     */
    public function set(Request $request, Response $response, array $args = []): Response
    {
        $albumId = $args['album'] ?? null;
        $apiToken = $this->settings['api_token'];
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? null;

        $onlyPublic = !$token || $token !== $apiToken;
        $set = $this->service->getList(null, $albumId, $onlyPublic);

        return $this->json($response, $set);
    }
}
