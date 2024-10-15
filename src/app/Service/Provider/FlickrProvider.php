<?php

namespace App\Service\Provider;

use App\Model\Album;
use App\Service\Utilities;
use JsonException;
use OAuth\Common\Exception\Exception;
use OAuth\Common\Storage\Memory;
use OAuth\Common\Storage\Session;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth1\Token\StdOAuth1Token;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Samwilson\PhpFlickr\PhpFlickr;

class FlickrProvider implements ProviderInterface
{
    private PhpFlickr $phpFlickr;
    private bool $authenticated = false;

    private const ID = 'flickr';
    private const LABEL = 'Flickr';

    /**
     * @param array $conf
     */
    public function __construct(private array $conf)
    {
    }

    public function getId(): string
    {
        return self::ID;
    }

    public function getLabel(): string
    {
        return self::LABEL;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->conf['enabled'];
    }

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * @throws JsonException
     */
    public function getAlbums(): array
    {
        $albums = [];

        $sets = $this->getStoredSetList();

        if (!$sets && $this->authenticated) {
            $sets = $this->phpFlickr->photosets()->getList();
            file_put_contents($this->conf['cache_sets'], json_encode($sets, JSON_THROW_ON_ERROR));
        }

        if ($sets && isset($sets['photoset'])) {
            foreach ($sets['photoset'] as $set) {
                $album = new Album();
                $album->id = $set['id'];
                $album->label = is_string($set['title']) ? $set['title'] : $set['title']['_content'];
                $album->description = is_string($set['description']) ? $set['description'] : $set['description']['_content'];
                $album->cover = "https://farm{$set['farm']}.staticflickr.com/{$set['server']}/{$set['primary']}_{$set['secret']}_q.jpg";
                $album->photos = $set['photos'];
                $album->videos = $set['videos'];
                $albums[] = $album;
            }
        }

        return $albums;
    }

    /**
     * @throws Exception
     * @throws JsonException
     */
    public function init(): void
    {
        if (empty($this->conf['key']) || empty($this->conf['secret'])) {
            throw new Exception('Missing Flickr API key and/or secret');
        }

        // Create PhpFlickr.
        $this->phpFlickr = new PhpFlickr($this->conf['key'], $this->conf['secret']);

        $storedToken = null;

        if (is_file($this->conf['token_file'])) {
            $storedToken = json_decode(@file_get_contents($this->conf['token_file']), true, 512, JSON_THROW_ON_ERROR);
        }

        if ($storedToken && !empty($storedToken['accessToken']) && !empty($storedToken['accessTokenSecret'])) {
            // Add your access token to the storage.
            $token = new StdOAuth1Token();
            $token->setAccessToken($storedToken['accessToken']);
            $token->setAccessTokenSecret($storedToken['accessTokenSecret']);
            $storage = new Memory();
            $storage->storeAccessToken('Flickr', $token);

            // Give PhpFlickr the storage containing the access token.
            $this->phpFlickr->setOauthStorage($storage);
            $this->authenticated = true;
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     * @throws JsonException
     */
    public function authenticate(Request $request, Response $response): Response
    {
        $queryparams = $request->getQueryParams();
        $oauthToken = $queryparams['oauth_token'] ?? null;

        $storage = new Session();
        $this->phpFlickr->setOauthStorage($storage);

        if (!$oauthToken) {
            $callback = (string) $request->getUri();
            $url = $this->phpFlickr->getAuthUrl($this->conf['perms'], $callback);

            return $response
                ->withHeader('Location', (string) $url)
                ->withStatus(302);
        }

        $accessToken = $this->phpFlickr->retrieveAccessToken($queryparams['oauth_verifier'], $oauthToken);
        if (isset($accessToken) && $accessToken instanceof TokenInterface) {
            file_put_contents($this->conf['token_file'], json_encode([
                'accessToken' => $accessToken->getAccessToken(),
                'accessTokenSecret' => $accessToken->getAccessTokenSecret(),
            ], JSON_THROW_ON_ERROR));

            return Utilities::redirect('admin', $request, $response);
        }

        return $response->withStatus(400);
    }

    public function unAuthenticate(): bool
    {
        return (is_file($this->conf['token_file']) && unlink($this->conf['token_file']));
    }

    public function removeCache(string $album = null): bool
    {
        if (!$album) {
            if (is_file($this->conf['cache_sets'])) {
                $success = unlink($this->conf['cache_sets']);
            } else {
                $success = true;
            }
        } elseif ($album === '*') {
            $this->removeCache();
            $files = glob($this->conf['cache_set'] . '*.json');
            foreach ($files as $file) {
                unlink($file);
            }
            $success = true;
        } else {
            $fn = $this->conf['cache_set'] . $album . '.json';
            if (is_file($fn)) {
                $success = unlink($fn);
            } else {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * @throws JsonException
     */
    public function albumExists(string $album): bool
    {
        if (is_file($this->conf['cache_set'] . $album . '.json')) {
            return true;
        }
        $sets = $this->getStoredSetList();
        if ($sets && isset($sets['photoset'])) {
            $ids = array_map(static function ($set) { return $set['id']; }, $sets['photoset']);
            return in_array($album, $ids, true);
        }

        return false;
    }

    /**
     * @throws JsonException
     */
    public function getMedia(string $album, string $photo = null): ?array
    {
        $set = $this->getStoredSet($album);
        $photos = $this->getStoredAlbum($album);

        if (!$photos) {
            try {
                $this->init();
                if ($this->authenticated) {
                    $photos = $this->phpFlickr->photosets()->getPhotos($album, $set['owner'], 'date_taken, geo, tags, url_o, url_'.$this->conf['vb_size'].', url_z, url_c');
                }
            } catch (\JsonException|Exception $e) {
            }

            if (isset($photos['photo'])) {
                $photos['description'] = is_string($set['description']) ? $set['description'] : $set['description']['_content'];
                // calculate thumb parameters, originals are wrong in portrait
                foreach ($photos['photo'] as $i => $media) {
                    $this->calculateImageSizes($media);
                    $photos['photo'][$i] = $media;
                    // thumbnail
                    if ($media['id'] === $photos['primary']) {
                        $photos['thumbnail'] = $media;
                    }
                }
                file_put_contents($this->conf['cache_set'] . $album . '.json', json_encode($photos, JSON_THROW_ON_ERROR));
            }
        }

        if ($photo) {
            foreach ($photos['photo'] as $media) {
                // thumbnail
                if ($photo === $media['id']) {
                    $photos['thumbnail'] = $media;
                    break;
                }
            }
        }

        return $photos ?? null;
    }


    /**
     * @throws JsonException
     */
    private function getStoredSetList(): ?array
    {
        if (is_file($this->conf['cache_sets'])) {
            return json_decode(@file_get_contents($this->conf['cache_sets']), true, 512, JSON_THROW_ON_ERROR);
        }

        return null;
    }

    /**
     * @throws JsonException
     */
    private function getStoredAlbum(string $album): ?array
    {
        if (is_file($this->conf['cache_set'] . $album . '.json')){
            return json_decode(@file_get_contents($this->conf['cache_set'] . $album . '.json'), true, 512, JSON_THROW_ON_ERROR);
        }

        return null;
    }

    /**
     * @throws JsonException
     */
    private function getStoredSet(string $album): ?array
    {
        $sets = $this->getStoredSetList();
        if ($sets && isset($sets['photoset'])) {
            foreach ($sets['photoset'] as $set) {
                if ($set['id'] === $album) {
                    return $set;
                }
            }
        }

        return null;
    }

    private function calculateImageSizes(array &$photo): void
    {
        $width_o = (int) $photo['width_o'];
        $height_o = (int) $photo['height_o'];
        $portrait = ((isset($photo['height_z']) && (int) $photo['height_z'] > (int) $photo['width_z']) || ($height_o > $width_o));
        // landscape
        if (!$portrait) {
            $photo['th_h'] = $this->conf['th_size'];
            $photo['th_w'] = round(($this->conf['th_size'] * $width_o) / $height_o);
            $photo['th_mt'] = 0;
            $photo['th_ml'] = -round(($photo['th_w'] - $this->conf['th_size'])/2);
        }
        // portrait
        else {
            $photo['th_w'] = $this->conf['th_size'];
            if ($width_o > $height_o) {
                $photo['th_h'] = round(($this->conf['th_size'] * $width_o) / $height_o);
            } else {
                $photo['th_h'] = round(($this->conf['th_size'] * $height_o) / $width_o);
            }
            $photo['th_ml'] = 0;
            $photo['th_mt'] = -round(($photo['th_h'] - $this->conf['th_size'])/2);
        }
        // fallbacks
        $photo['url_vb'] = $photo['url_'.$this->conf['vb_size']] ?? $photo['url_o'];
        $photo['url_z'] = $photo['url_z'] ?? $photo['url_o'];
        $photo['width_vb'] = $photo['width_'.$this->conf['vb_size']] ?? $width_o;
        $photo['height_vb'] = $photo['height_'.$this->conf['vb_size']] ?? $height_o;
    }
}
