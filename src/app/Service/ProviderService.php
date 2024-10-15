<?php

namespace App\Service;

use App\Service\Provider\FlickrProvider;
use App\Service\Provider\ProviderInterface;
use OAuth\Common\Exception\Exception;
use RuntimeException;

class ProviderService
{
    private array $providers = [];

    /**
     * @return array
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getProvider(string $provider)
    {
        return $this->providers[$provider];
    }

    public function getProviderByAlbumId(string $album): ?ProviderInterface
    {
        /** @var $provider ProviderInterface */
        foreach ($this->getProviders() as $provider) {
            if ($provider && $provider->isEnabled() && $provider->albumExists($album)) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * @param string $directory
     * @return void
     */
    public function ensureDirectoriesExists(array $settings): void
    {
        // create dir if not exist
        if (!is_dir($settings['cacheDir']) && !mkdir($concurrentDirectory = $settings['cacheDir']) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
        if (!is_dir($settings['tokenDir']) && !mkdir($concurrentDirectory = $settings['tokenDir']) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
        }
    }

    /**
     * @param array|null $settings
     * @return void
     */
    public function initProviders(array $settings = null, bool $initApiService = false): void
    {
        $providers = [];

        if (is_array($settings)) {
            foreach ($settings as $key => $conf) {
                if ($conf['enabled']) {
                    $provider = match ($key) {
                        'flickr' => new FlickrProvider($conf),
                        default => null,
                    };
                    if ($provider && $initApiService) {
                        try {
                            $provider->init();
                        } catch (\JsonException|Exception $e) {

                        }
                    }
                    if ($provider) {
                        $providers[$key] = $provider;
                    }
                }
            }
        }

        $this->providers = $providers;
    }


}
