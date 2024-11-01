<?php

namespace App\Service\Api;

use App\Enum\ItemSizeEnum;
use App\Enum\ItemTypeEnum;
use App\Enum\ProviderEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\PhotoSize;
use App\Model\Provider;
use App\Service\BaseService;
use App\Service\ImageService;
use App\Service\Utilities;
use DateTime;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class DiskService extends BaseService implements ApiInterface
{
    private Provider $provider;
    private array $conf;
    private string $importPath;
    private string $cachePath;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->conf = $this->settings['providers'][ProviderEnum::DISK->value];
        $this->importPath = $this->settings['documentRoot'] . $this->conf['importPath'];
        $this->cachePath = $this->settings['rootPath'] . $this->conf['cachePath'];
    }

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function init(): bool
    {
        // check directories
        Utilities::ensureDirectoryExists($this->importPath);
        Utilities::ensureDirectoryExists($this->cachePath);

        return true;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function authenticate(Request $request, Response $response): Response
    {
        return $response->withStatus(400);
    }

    public function unAuthenticate(): bool
    {
        return true;
    }

    public function getAlbums(): array
    {
        $albums = [];
        $sets = $this->getListOnDisk($this->importPath);
        foreach ($sets as $i => $set) {
            $items = $this->getListOnDisk($set['path']);
            $names = array_column($items, 'name');
            $images = preg_grep($this->conf['accept_image_file_types'], $names);
            $videos = preg_grep($this->conf['accept_video_file_types'], $names);

            $album = new Album();
            $album->fid = $set['name'];
            $album->setProvider($this->provider);
            $album->title = ucfirst($set['name']);
            if (count($images)) {
                $album->cover = $images[0];
                $coverItem = new Photo();
                $coverItem->fid = $images[0];
                $coverItem->url = $this->getItemUrl($album->fid, $images[0]);
                $album->setCoverItem($coverItem);
            }
            $album->photos = count($images);
            $album->videos = count($videos);
            $album->sort = $i;
            $albums[] = $album;
        }

        return $albums;
    }

    public function getItems(Album $album): array
    {
        $itemsList = $this->getItemsByAlbumFid($album->fid);
        $items = [];

        foreach ($itemsList as $i => $media) {
            if (!$this->isAllowedImage($media['name'])) {
                continue;
            }
            $meta = $this->getImageProperties($media['path']);
            $item = new Photo();
            $item->id = Utilities::uid();
            $item->fid = $media['name'];
            if (isset($album->id)) {
                $item->album = $album->id;
            }
            $item->title = ucfirst($media['name']);
            $item->type = ItemTypeEnum::IMAGE;
            if (isset($meta['exif']['DateTimeOriginal'])) {
                $datetime = DateTime::createFromFormat('Y:m:d H:i:s', $meta['exif']['DateTimeOriginal']);
                $item->datetaken = $datetime->format('Y-m-d H:i:s');
            }
            $item->url = $this->getItemUrl($album->fid, $item->fid);
            $item->width = $meta['size'][0];
            $item->height = $meta['size'][1];
            $item->sizes = $this->mapSizes($item);
            $item->sort = $i;
            $items[] = $item;
        }

        return $items;
    }

    public function getItemsFidList(Album $album): array
    {
        $itemsList = $this->getItemsByAlbumFid($album->fid);

        return array_column($itemsList, 'name');
    }

    public function albumIsDifferent(Album $album, Album $compareAlbum): bool
    {
        return $album->photos !== $compareAlbum->photos ||
            $album->videos !== $compareAlbum->videos;
    }

    public function readFile(Album $album, Photo $item, ItemSizeEnum $sizeEnum = null): ?array
    {
        $path = "{$this->importPath}/{$album->fid}/{$item->fid}";
        if (!is_file($path)) {
            return null;
        }
        $source = pathinfo($path);

        if ($sizeEnum && isset($item->sizes[$sizeEnum->value])) {
            $size = $item->sizes[$sizeEnum->value];
            $img = ImageService::create($path);
            if ($sizeEnum->isSquare()) {
                $img->cropThumbnailImage($size->width, $size->height);
            }
            else {
                $img->thumbnailImage($size->width, $size->height);
            }
            ImageService::autorotate($img);
            $path = $this->cachePath . '/' . $this->cacheFileName($sizeEnum, $item, $source['extension']);
            $albumPath = dirname($path);
            Utilities::ensureDirectoryExists($albumPath);
            file_put_contents($path, $img);
            $img->clear();
        }

        $source['resource'] = fopen($path, 'rb');

        return $source;
    }

    public function clearCache(Album $album): void
    {
        $path = "{$this->cachePath}/{$album->id}";

        Utilities::deleteDir($path);
    }

    public function fixAlbum(string $albumFid): string
    {
        $path = "$this->importPath/$albumFid";

        if (is_dir($path)) {
            $albumFid = Utilities::safeFn($this->importPath, $albumFid);
        }

        $itemsList = $this->getItemsByAlbumFid($albumFid);

        foreach ($itemsList as $media) {
            if (!is_file($media['path']) || !$this->isAllowedImage($media['name'])) {
                continue;
            }
            Utilities::safeFn($path, $media['name']);
        }

        return $albumFid;
    }

    public function mapSizes(Photo $item): array
    {
        $sizes = [];
        foreach (ItemSizeEnum::cases() as $sizeEnum) {
            $providerSize = $this->conf['sizes'][$sizeEnum->value];
            $itemSizes = [$item->width, $item->height];
            if (!$providerSize) {
                continue;
            }
            if ($providerSize > max($itemSizes)) {
                $size = new PhotoSize();
                $size->url = $item->url;
                $size->width = $item->width;
                $size->height = $item->height;
            } else {
                $size = $this->getSize($sizeEnum, $item);
            }

            $sizes[$sizeEnum->value] = $size;
        }

        return $sizes;
    }

    private function getItemUrl(string $album, string $item): string
    {
        return "{$this->conf['importPath']}/{$album}/{$item}";
    }

    private function cacheFileName(ItemSizeEnum $sizeEnum, Photo $item, string $ext): string
    {
        return "{$item->album}/{$item->id}_{$sizeEnum->value}." . strtolower($ext);
    }

    private function isMediaFile($file): bool
    {
        $mime = mime_content_type($file);

        return (str_contains($mime, 'video/') || str_contains($mime, 'image/'));
    }

    private function isAllowedImage(string $name): bool
    {
        $images = preg_grep($this->conf['accept_image_file_types'], [$name]);

        return count($images) === 1;
    }

    private function getListOnDisk(string $path): array
    {
        $data = [];
        if (!is_dir($path)) {
            $this->logger->error('Read list failed from: ' . $path);
            return $data;
        }
        // read content into array
        $list = scandir($path);
        // loop
        foreach ($list as $item) {
            $itemPath = "$path/$item";
            if (
                $item === '.DS_Store' ||
                $item === '.' ||
                $item === '..' ||
                (is_file($itemPath) && !$this->isMediaFile($itemPath))
            ) {
                continue;
            }

            $data[] = [
                'name' => $item,
                'path' => $itemPath,
            ];
        }

        return $data;
    }

    private function getImageProperties(string $path): array
    {
        $img = ImageService::create($path);
        $exifArray = $img->getImageProperties("exif:*");
        ImageService::autorotate($img);
        $size = [
            $img->getImageWidth(),
            $img->getImageHeight()
        ];

        $img->clear();

        $exif = [];
        foreach ($exifArray as $key => $value) {
            $eKey = ltrim($key, 'exif:');
            $exif[$eKey] = trim($value);
        }

        return [
            'size' => $size,
            'exif' => $exif,
        ];
    }

    private function getItemsByAlbumFid(string $albumFid): array
    {
        $path = "$this->importPath/$albumFid";

        if (!is_dir($path)) {
            throw new RuntimeException("Album $albumFid not found");
        }

        return $this->getListOnDisk($path);
    }

    private function getSize(ItemSizeEnum $sizeEnum, Photo $item): PhotoSize
    {
        $size = new PhotoSize();
        $ext = pathinfo($item->url, PATHINFO_EXTENSION);
        $size->url = $this->baseUrl . $this->conf['cachePath'] . '/' . $this->cacheFileName($sizeEnum, $item, $ext);
        $providerSize = $this->conf['sizes'][$sizeEnum->value];

        if ($sizeEnum->isSquare()) {
            $size->width = $providerSize;
            $size->height = $providerSize;
        }
        elseif ($item->width >= $item->height) {
            $size->width = $providerSize;
            $size->height = round(($providerSize / $item->width) * $item->height);
        }
        else {
            $size->height = $providerSize;
            $size->width = round(($providerSize / $item->height) * $item->width);
        }

        return $size;
    }
}
