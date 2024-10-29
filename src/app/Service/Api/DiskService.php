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
use ImagickException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;

class DiskService extends BaseService implements ApiInterface
{
    private Provider $provider;
    private array $conf;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->conf = $this->settings['providers'][ProviderEnum::DISK->value];
    }

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function getImportMaxSize(): ?int
    {
        return $this->conf['import_max_count'];
    }

    public function init(): bool
    {
        // check directories
        $importPath = $this->settings['rootPath'] . $this->conf['import_path'];
        if (!file_exists($importPath) && !mkdir($importPath, 0777, true) && !is_dir($importPath)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $importPath));
        }

        $cachePath = $this->settings['rootPath'] . $this->conf['cache_path'];
        if (!file_exists($cachePath) && !mkdir($cachePath, 0777, true) && !is_dir($cachePath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $cachePath));
        }

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
        $sets = $this->getListOnDisk($this->conf['import_path']);
        foreach ($sets as $i => $set) {
            $items = $this->getListOnDisk($set['path']);
            $names = array_column($items, 'name');
            $images = preg_grep($this->conf['accept_image_file_types'], $names);
            $videos = preg_grep($this->conf['accept_video_file_types'], $names);

            $album = new Album();
            $album->fid = $set['name'];
            $album->setProvider($this->provider);
            $album->title = $set['name'];
            if (count($images)) {
                $album->cover = $images[0];
                $coverItem = new Photo();
                $coverItem->fid = $images[0];
                $coverItem->url = $this->getItemUrl("{$set['path']}/{$images[0]}");
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
        $itemsList = $this->getItemsByAlbumId($album->fid);
        $items = [];

        foreach ($itemsList as $i => $media) {
            if (!$this->isImage($media['name'])) {
                continue;
            }
            $meta = $this->getImageProperties($media['fullPath']);
            $item = new Photo();
            $item->id = Utilities::uid();
            $item->fid = $media['name'];
            if (isset($album->id)) {
                $item->album = $album->id;
            }
            $item->title = $media['name'];
            $item->type = ItemTypeEnum::IMAGE;
            if (isset($meta['exif']['DateTimeOriginal'])) {
                $datetime = DateTime::createFromFormat('Y:m:d H:i:s', $meta['exif']['DateTimeOriginal']);
                $item->datetaken = $datetime->format('Y-m-d H:i:s');
            }
            $item->url = $this->getItemUrl($media['path']);
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
        $itemsList = $this->getItemsByAlbumId($album->fid);

        return array_column($itemsList, 'name');
    }

    public function albumIsDifferent(Album $album, Album $compareAlbum): bool
    {
        return $album->photos !== $compareAlbum->photos ||
            $album->videos !== $compareAlbum->videos;
    }

    public function readFile(Album $album, Photo $item, ItemSizeEnum $sizeEnum = null): array
    {
        $path = "{$this->settings['rootPath']}{$this->conf['import_path']}/{$album->fid}/{$item->fid}";
        $source = pathinfo($path);

        if ($sizeEnum && isset($item->sizes[$sizeEnum->value])) {
            $size = $item->sizes[$sizeEnum->value];
            $img = ImageService::create($path);
            if ($sizeEnum->value === 'sq150') {
                $img->cropThumbnailImage($size->width, $size->height);
            }
            else {
                $img->thumbnailImage($size->width, $size->height);
            }
            ImageService::autorotate($img);
            $path = $this->settings['rootPath'] . $this->cacheFileName($sizeEnum, $item, $source['extension']);
            $albumPath = dirname($path);
            if (!file_exists($albumPath) && !mkdir($albumPath, 0777, true) && !is_dir($albumPath)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $albumPath));
            }
            file_put_contents($path, $img);
            $img->clear();
        }

        $source['resource'] = fopen($path, 'rb');

        return $source;
    }

    public function clearCache(Album $album): void
    {
        $path = $this->settings['rootPath'] . "{$this->conf['cache_path']}/{$album->id}";

        Utilities::rmdir($path);
    }

    private function getItemUrl($item): string
    {
        return "{$this->baseUrl}{$item}";
    }

    private function getListOnDisk(string $pathPart): array
    {
        $data = [];
        $path = "{$this->settings['rootPath']}$pathPart";
        if (!is_dir($path)) {
            $this->logger->error('Read list failed from: ' . $path);
            return $data;
        }

        // read content into array
        $list = scandir($path);
        // loop
        foreach ($list as $item) {
            $subpath = "$path/$item";
            if ($item === '.DS_Store' || $item === '.' || $item === '..' || !file_exists($subpath)) {
                continue;
            }

            $data[] = [
                'name' => urlencode($item),
                'path' => "$pathPart/$item",
                'fullPath' => $subpath,
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

    private function isImage(string $name): bool
    {
        $images = preg_grep($this->conf['accept_image_file_types'], [$name]);

        return count($images) === 1;
    }

    private function getItemsByAlbumId(string $albumId): array
    {
        $sets = $this->getListOnDisk($this->conf['import_path']);
        $names = array_column($sets, 'name');
        $i = array_search($albumId, $names, true);
        if ($i === false) {
            throw new RuntimeException("Album $albumId not found");
        }
        $set = $sets[$i];

        return $this->getListOnDisk($set['path']);
    }

    private function mapSizes(Photo $item): array
    {
        $sizes = [];
        foreach (ItemSizeEnum::cases() as $sizeEnum) {
            $providerSize = $this->conf['sizes'][$sizeEnum->value];
            $itemSizes = [$item->width, $item->height];
            if (!$providerSize || $providerSize > max($itemSizes)) {
                continue;
            }
            $size = $this->getSize($sizeEnum, $item);
            $sizes[$sizeEnum->value] = $size;
        }

        return $sizes;
    }

    private function getSize(ItemSizeEnum $sizeEnum, Photo $item): PhotoSize
    {
        $size = new PhotoSize();
        $ext = pathinfo($item->url, PATHINFO_EXTENSION);
        $size->url = $this->baseUrl . $this->cacheFileName($sizeEnum, $item, $ext);
        $providerSize = $this->conf['sizes'][$sizeEnum->value];

        if ($sizeEnum->value === 'sq150') {
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

    private function cacheFileName(ItemSizeEnum $sizeEnum, Photo $item, string $ext): string
    {
        $path = $this->conf['cache_path'];

        return "$path/{$item->album}/{$item->id}_{$sizeEnum->value}." . strtolower($ext);
    }
}
