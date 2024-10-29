<?php

namespace App\Controller;

use App\Enum\ItemSizeEnum;
use App\Enum\ProviderEnum;
use App\Service\AlbumService;
use App\Service\Api\DiskService;
use App\Service\Api\FlickrService;
use App\Service\ImageService;
use App\Service\ItemService;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ImageController extends BaseController
{
    public function __construct(
        ContainerInterface $container,
        protected readonly AlbumService $albumService,
        protected readonly ItemService $itemService,
        protected readonly ImageService $imgeService,
        protected readonly FlickrService $flickrService,
        protected readonly DiskService $diskService,
    )
    {
        parent::__construct($container);
    }

    public function image(Request $request, Response $response, array $args = []): Response
    {
        $this->itemService->setBaseUrl($request->getAttribute('base_url'));
        $this->albumService->setBaseUrl($request->getAttribute('base_url'));
        $itemId = $args['item'] ?? null;
        $sizeId = $args['size'] ?? null;
        $item = $this->itemService->get($itemId);
        $file = null;

        if ($item) {
            $set = $this->albumService->getList(null, $item->album);
            if (count($set) === 1) {
                $providerEnum = ProviderEnum::from($set[0]->getProvider()->getId());
                $providerApi = match ($providerEnum) {
                    ProviderEnum::FLICKR => $this->flickrService,
                    ProviderEnum::DISK => $this->diskService,
                };

                $file = $providerApi?->readFile($set[0], $item, ItemSizeEnum::from($sizeId));
            }
        }

        if ($file) {
            return $this->file($request, $response, $file);
        }

        return $response->withStatus(404);
    }
}
