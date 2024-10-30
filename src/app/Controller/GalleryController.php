<?php

namespace App\Controller;

use App\Enum\ItemSizeEnum;
use App\Enum\ProviderEnum;
use App\Model\Photo;
use App\Service\AlbumService;
use App\Service\Api\DiskService;
use App\Service\Api\FlickrService;
use App\Service\ItemService;
use App\Service\Utilities;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;

class GalleryController extends BaseController
{
    public function __construct(
        ContainerInterface $container,
        protected AlbumService $albumService,
        protected ItemService $itemService,
        protected readonly FlickrService $flickrService,
        protected readonly DiskService $diskService,
    )
    {
        parent::__construct($container);
    }

    public function album(Request $request, Response $response, array $args = []): Response
    {
        $albumId = $args['album'] ?? null;
        $itemId = $args['item'] ?? null;
        if (!$albumId) {
            throw new HttpNotFoundException($request);
        }
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $album = $this->albumService->get($albumId);
        if ($album) {
            $items = $this->itemService->getList($album);

            return $this->render($request, $response, 'album.twig', [
                'page' => 'set',
                'album' => $album,
                'itemId' => $itemId,
                'items' => $items
            ]);
        }

        return $response->withStatus(404);
    }

    public function photo(Request $request, Response $response, array $args = []): Response
    {
        $albumId = $args['album'] ?? null;
        $itemId = $args['item'] ?? null;
        if (!$albumId || !$itemId) {
            throw new HttpNotFoundException($request);
        }

        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $item = $this->itemService->get($itemId);

        if ($item) {
            $queryParams = $request->getQueryParams();
            if (isset($queryParams['download'])) {
                return $this->download($request, $response, $item);
            }

            return $this->render($request, $response, 'photo.twig', [
                'page' => 'photo',
                'item' => $item,
            ]);
        }

        return $response->withStatus(404);
    }

    private function download(Request $request, Response $response, Photo $item): Response
    {
        $file = null;
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $album = $this->albumService->get($item->album);
        if ($album) {
            $providerEnum = ProviderEnum::from($album->getProvider()->getId());
            $providerApi = match ($providerEnum) {
                ProviderEnum::FLICKR => $this->flickrService,
                ProviderEnum::DISK => $this->diskService,
            };

            $file = $providerApi?->readFile($album, $item);
        }

        if ($file) {
            return $this->file($request, $response, $file, true);
        }

        return $response->withStatus(404);
    }
}
