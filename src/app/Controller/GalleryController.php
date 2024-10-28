<?php

namespace App\Controller;

use App\Service\AlbumService;
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
    )
    {
        parent::__construct($container);
    }

    public function album(Request $request, Response $response, array $args = []): Response
    {
        $albumFid = $args['album'] ?? null;
        if ($albumFid) {
            $this->albumService->setBaseUrl($request->getAttribute('base_url'));
            $this->itemService->setBaseUrl($request->getAttribute('base_url'));
            $album = $this->albumService->getByFid($albumFid, true);
            if ($album) {
                $items = $this->itemService->getList($album);

                return $this->render($request, $response, 'album.twig', [
                    'page' => 'set',
                    'album' => $album,
                    'items' => $items
                ]);
            }
        }

        return $response->withStatus(404);
    }

    public function photo(Request $request, Response $response, array $args = []): Response
    {
        $album = $args['album'];
        $photo = $args['photo'];
        if (!$album || !$photo) {
            throw new HttpNotFoundException($request);
        }
        $provider = $this->albumService->getProviderByAlbumId($album);

        if ($provider) {
            $photoset = $provider->getMedia($album, $photo);
            if (!$photoset) {
                throw new HttpNotFoundException($request);
            }

            return $this->render($request, $response, 'photo.twig', [
                'page' => 'photo',
                'title' => $photoset['thumbnail']['title'],
                'photo' => $photoset['thumbnail'],
                'set' => $photoset,
            ]);
        }

        return $response->withStatus(404);
    }

    public function download(Request $request, Response $response, array $args = []): Response
    {
        $album = $args['album'];
        $photo = $args['photo'];
        if (!$album || !$photo) {
            throw new HttpNotFoundException($request);
        }
        $provider = $this->albumService->getProviderByAlbumId($album);

        if ($provider) {
            $photoset = $provider->getMedia($album, $photo);
            if (!$photoset) {
                throw new HttpNotFoundException($request);
            }
            $src = $photoset['thumbnail']['url_o'] ?? null;

            if ($src) {
                $photoHandle = Utilities::download($src, $this->settings['download']['referer']);
                $filename = basename($src);
                $file_extension = strtolower(substr(strrchr($filename,'.'),1));
                $ctype = match ($file_extension) {
                    'gif' => 'image/gif',
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpg',
                    default => throw new HttpNotFoundException($request)
                };
                $stat = fstat($photoHandle);

                return $response->withHeader('Content-type', $ctype)
                    ->withHeader('Content-Disposition', 'attachment; filename="'.$filename.'"')
                    ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                    ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
                    ->withHeader('Pragma', 'no-cache')
                    ->withHeader('Content-length', $stat['size'])
                    ->withBody((new Stream($photoHandle)));
            }
        }

        return $response->withStatus(404);
    }
}
