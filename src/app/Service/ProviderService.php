<?php

namespace App\Service;

use App\Enum\ItemChangeEnum;
use App\Enum\ProviderEnum;
use App\Model\Album;
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

    public function diff(ProviderEnum $providerEnum): array
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $provider = $this->getProvider($providerEnum);
        $dbData = $this->albumService->getList($provider);
        $providerData = $apiService?->getAlbums();

        return $this->diffAlbums($providerEnum, $dbData, $providerData);
    }

    public function sync(ProviderEnum $providerEnum, string $albumId = null): bool
    {
        $apiService = $this->getProviderApiService($providerEnum);
        $provider = $this->getProvider($providerEnum);

        /** @var Album $album */
        foreach ($this->diff($providerEnum) as $album) {
            if ($albumId && $albumId !== $album->fid) {
                continue;
            }
            try {
                switch ($album->getItemChange()) {
                    case ItemChangeEnum::NEW:
                        $items = $apiService?->getItems($album);
                        $this->albumService->create($album);
                        $this->albumService->import($provider, $album, $items);
                        break;
                    case ItemChangeEnum::CHANGE:
                        $items = $apiService?->getItems($album);
                        $this->albumService->update($album);
                        $this->albumService->import($provider, $album, $items);
                        break;
                    case ItemChangeEnum::DELETE:
                        $this->itemService->deleteAll($album);
                        $this->albumService->delete($album);
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
            $album->setItemChange(ItemChangeEnum::DELETE);
            $data[] = $album;
        }
        /** @var Album $album */
        foreach ($providerData as $key => $album) {
            $dbAlbum = Utilities::findObjectBy($dbData, 'fid', $album->fid);
            if (!$dbAlbum) {
                $album->id = Utilities::uid();
                $album->sort = $key;
                $album->setItemChange(ItemChangeEnum::NEW);
            } else {
                $dbAlbumItemsList = $this->itemService->getFidList($dbAlbum);
                $albumItemsList = $apiService?->getItemsFidList($album);
                if ($apiService?->albumIsDifferent($album, $dbAlbum) || count(array_diff($dbAlbumItemsList, $albumItemsList)) > 0) {
                    $album->id = $dbAlbum->id;
                    $album->sort = $key;
                    $album->setItemChange(ItemChangeEnum::CHANGE);
                }
                else {
                    $album->id = $dbAlbum->id;
                    $album->setItemChange(ItemChangeEnum::OK);
                }
            }
            $data[] = $album;
        }

        return $data;
    }

}
