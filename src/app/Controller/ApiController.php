<?php

namespace App\Controller;

use App\Enum\ItemSizeEnum;
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

    public function sizes(Request $request, Response $response, array $args = []): Response
    {
        $sizes = [];

        foreach (ItemSizeEnum::cases() as $size) {
            $sizes[$size->value] = $size->label();
        }

        return $this->json($response, $sizes);
    }

    /**
     * @throws JsonException
     */
    public function set(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $albumId = $args['album'] ?? null;
        $apiToken = $this->settings['api_token'];
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? null;

        $onlyPublic = !$token || $token !== $apiToken;
        $set = $this->service->getList(null, $albumId, null, $onlyPublic);

        return $this->json($response, $set);
    }

    /**
     * @throws JsonException
     */
    public function item(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $albumId = $args['album'] ?? null;
        $itemId = $args['item'] ?? null;
        $apiToken = $this->settings['api_token'];
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? null;

        $onlyPublic = !$token || $token !== $apiToken;
        $set = $this->service->getList(null, $albumId, null, $onlyPublic);
        $items = [];
        if (count($set) === 1) {
            $items = $this->service->getItemsList($set[0], $itemId);
        }

        return $this->json($response, $items);
    }
}
