<?php

namespace App\Controller;

use App\Enum\ItemSizeEnum;
use App\Service\AlbumService;
use App\Service\ItemService;
use JsonException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiController extends BaseController
{
    public function __construct(
        ContainerInterface $container,
        protected readonly AlbumService $albumService,
        protected readonly ItemService $itemService,
    )
    {
        parent::__construct($container);
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        return $this->render($request, $response, 'api.twig', [
            'page' => 'admin',
            'token' => $this->settings['api_token']
        ]);
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
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $albumId = $args['album'] ?? null;
        $onlyPublic = $this->validateToken($request);

        $set = $this->albumService->getList(null, $albumId, null, $onlyPublic);

        return $this->json($response, $set);
    }

    /**
     * @throws JsonException
     */
    public function item(Request $request, Response $response, array $args = []): Response
    {
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $albumId = $args['album'] ?? null;
        $itemId = $args['item'] ?? null;
        $onlyPublic = $this->validateToken($request);

        $set = $this->albumService->getList(null, $albumId, null, $onlyPublic);
        $items = [];
        if (count($set) === 1) {
            $items = $this->itemService->getList($set[0], $itemId);
        }

        return $this->json($response, $items);
    }
}
