<?php

namespace App\Service;

use App\Enum\ItemStatusEnum;
use App\Enum\ProviderEnum;
use App\Model\Album;
use App\Model\ItemStatus;
use App\Model\Provider;
use App\Service\Api\FlickrService;
use App\Service\Api\DiskService;
use App\Service\Api\ApiInterface;
use OAuth\Common\Exception\Exception;
use Psr\Container\ContainerInterface;

class ProviderService extends BaseService
{
    /** @var Provider[] */
    private array $providers = [];

    public function __construct(
        ContainerInterface $container,
        protected readonly ItemService $itemService,
        protected readonly AlbumService $albumService,
        protected readonly FlickrService $flickrService,
        protected readonly DiskService $diskService,
    )
    {
        parent::__construct($container);
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
        $this->flickrService->setBaseUrl($baseUrl);
        $this->diskService->setBaseUrl($baseUrl);
        $this->albumService->setBaseUrl($baseUrl);
        $this->itemService->setBaseUrl($baseUrl);
    }

    /**
     * @return Provider[]
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getItemService(): ItemService
    {
        return $this->itemService;
    }

    public function getAlbumService(): AlbumService
    {
        return $this->albumService;
    }

    public function getProviderApiService(ProviderEnum $providerEnum): ?ApiInterface
    {
        return match ($providerEnum) {
            ProviderEnum::FLICKR => $this->flickrService,
            ProviderEnum::DISK => $this->diskService,
        };
    }

    /**
     * @param ProviderEnum $providerEnum
     * @return Provider
     */
    public function getProvider(ProviderEnum $providerEnum): Provider
    {
        return $this->providers[$providerEnum->value];
    }

    /**
     * @return void
     */
    public function initProviders(): void
    {
        $providers = [];

        foreach ($this->settings['providers'] as $key => $conf) {
            if ($conf['enabled']) {
                try {
                    $providerEnum = ProviderEnum::from($key);
                } catch (\Exception $e) {
                    $this->logger->error('Init providers error: ' . $e->getMessage());
                    continue;
                }

                $provider = new Provider($providerEnum, $conf);
                $providerApiService = $this->getProviderApiService($providerEnum);
                $init = false;
                if ($providerApiService) {
                    try {
                        $providerApiService->setProvider($provider);
                        $init = $providerApiService->init();
                    } catch (Exception $e) {
                        $this->logger->warning('Init provider failed: ' . $e->getMessage());
                        continue;
                    }
                }
                $provider->setAuthenticated($init);
                $providers[$providerEnum->value] = $provider;
            }
        }

        $this->providers = $providers;
    }

    public function diff(ProviderEnum $providerEnum, string $albumFid = null): array
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $provider = $this->getProvider($providerEnum);
        $dbData = $this->albumService->getList($provider, null, $albumFid);
        $providerData = $apiService?->getAlbums();

        if ($albumFid) {
            $providerData = array_filter($providerData, static fn($album) => $album->fid === $albumFid);
        }

        return $this->diffAlbums($providerEnum, $dbData, $providerData);
    }

    public function sync(ProviderEnum $providerEnum, string $albumFid): bool
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $provider = $this->getProvider($providerEnum);
        $max = $apiService?->getImportMaxSize() ?: 1000;

        /** @var Album $album */
        $i = 0;
        foreach ($this->diff($providerEnum, $albumFid) as $album) {
            if ($albumFid && $albumFid !== $album->fid) {
                continue;
            }
            if ($i >= $max) {
                break;
            }
            try {
                switch ($album->getStatus()?->type) {
                    case ItemStatusEnum::NEW:
                        $items = $apiService?->getItems($album);
                        $this->albumService->create($album);
                        $this->albumService->import($provider, $album, $items);
                        $i++;
                        break;
                    case ItemStatusEnum::CHANGE:
                        $items = $apiService?->getItems($album);
                        $this->albumService->update($album);
                        $this->albumService->import($provider, $album, $items);
                        $i++;
                        break;
                    case ItemStatusEnum::DELETE:
                        $apiService?->clearCache($album);
                        $this->itemService->deleteAll($album);
                        $this->albumService->delete($album);
                        $i++;
                        break;
                }
            } catch (\Exception $e) {
                $this->logger->error("Provider album sync failed ($album->id): " . $e->getMessage());
            }
        }

        return true;
    }

    public function autorotate(ProviderEnum $providerEnum, string $albumId): bool
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $provider = $this->getProvider($providerEnum);

        if (!$albumId || !$provider->isEditable()) {
            return false;
        }

        $apiService?->autorotate($albumId);

        return true;
    }

    private function diffAlbums(ProviderEnum $providerEnum, array $dbData, $providerData): array
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $data = [];

        $forDeletion = array_filter($dbData, static function($album) use ($providerData) {
            $item = Utilities::findObjectBy($providerData, 'fid', $album->fid);
            return $item === null;
        });

        /** @var Album $album */
        foreach ($forDeletion as $album) {
            $album->setStatus(new ItemStatus(ItemStatusEnum::DELETE));
            $data[] = $album;
        }
        /** @var Album $album */
        foreach ($providerData as $key => $album) {
            /** @var Album $dbAlbum */
            $dbAlbum = Utilities::findObjectBy($dbData, 'fid', $album->fid);
            $itemStatus = null;
            if (!$dbAlbum) {
                $album->id = Utilities::uid();
                $album->sort = $key;
                $itemStatus = new ItemStatus(ItemStatusEnum::NEW);
            } else {
                $dbAlbumItemsList = $this->itemService->getFidList($dbAlbum);
                $albumItemsList = $apiService?->getItemsFidList($album);
                if ($apiService?->albumIsDifferent($album, $dbAlbum) || count(array_diff($dbAlbumItemsList, $albumItemsList)) > 0) {
                    $album->id = $dbAlbum->id;
                    $album->sort = $key;
                    $itemStatus = new ItemStatus(ItemStatusEnum::CHANGE, [
                        'photos' => $dbAlbum->photos,
                        'videos' => $dbAlbum->videos
                    ]);
                }
                else {
                    $album->id = $dbAlbum->id;
                    $itemStatus = new ItemStatus(ItemStatusEnum::OK);
                }
            }
            $album->setStatus($itemStatus);
            $data[] = $album;
        }

        return $data;
    }

}
