<?php

namespace App\Controller;

use App\Service\AlbumService;
use App\Service\ItemService;
use App\Service\Utilities;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;

class LegacyGalleryController extends BaseController
{
    public function __construct(
        ContainerInterface $container,
        protected AlbumService $albumService,
        protected ItemService $itemService,
    )
    {
        parent::__construct($container);
    }

    public function album(Request $request, Response $response, array $args = []): Response
    {
        $albumFid = $args['album'] ?? null;
        $photoFid = $args['photo'] ?? null;
        if (!$albumFid) {
            throw new HttpNotFoundException($request);
        }
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $album = $this->albumService->getByFid($albumFid, true);
        if (!$album) {
            throw new HttpNotFoundException($request);
        }
        $newArgs = ['album' => $album->id];
        if ($photoFid) {
            if ($item = $this->itemService->getByFid($album, $photoFid)) {
                $newArgs['item'] = $item->id;
            }
        }

        return $this->redirect($request, $response, 'album', $newArgs);
    }

    public function photo(Request $request, Response $response, array $args = []): Response
    {
        return $this->handleItemRequest($request, $response, $args, 'album');
    }

    public function download(Request $request, Response $response, array $args = []): Response
    {
        return $this->handleItemRequest($request, $response, $args, 'download');
    }

    private function handleItemRequest(Request $request, Response $response, array $args, string $route): Response
    {
        $albumFid = $args['album'] ?? null;
        $photoFid = $args['photo'] ?? null;
        if (!$albumFid || !$photoFid) {
            throw new HttpNotFoundException($request);
        }
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $album = $this->albumService->getByFid($albumFid, true);
        if (!$album) {
            throw new HttpNotFoundException($request);
        }
        $item = $this->itemService->getByFid($album, $photoFid);
        if (!$item) {
            throw new HttpNotFoundException($request);
        }

        return $this->redirect($request, $response, $route, [
            'album' => $album->id,
            'item' => $item->id,
        ]);
    }
}
