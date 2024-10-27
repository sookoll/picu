<?php

namespace App\Service\Api;

use App\Enum\ItemTypeEnum;
use App\Enum\ProviderEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\Provider;
use App\Service\BaseService;
use App\Service\Utilities;
use DateTime;
use JsonException;
use OAuth\Common\Exception\Exception;
use OAuth\Common\Storage\Memory;
use OAuth\Common\Storage\Session;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth1\Token\StdOAuth1Token;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Samwilson\PhpFlickr\PhpFlickr;

class FlickrService extends BaseService implements ApiInterface
{
    private PhpFlickr $phpFlickr;
    private Provider $provider;
    private array $conf;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->conf = $this->settings['providers'][ProviderEnum::FLICKR->value];
    }

    public function setProvider(Provider $provider): void
    {
        $this->provider = $provider;
    }

    public function init(): bool
    {
        if (empty($this->conf['key']) || empty($this->conf['secret'])) {
            throw new Exception('Missing Flickr API key and/or secret');
        }

        // Create PhpFlickr.
        $this->phpFlickr = new PhpFlickr($this->conf['key'], $this->conf['secret']);

        $storedToken = null;

        if (is_file($this->conf['token_file'])) {
            try {
                $storedToken = json_decode(@file_get_contents($this->conf['token_file']), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Exception('Error on reading token from disk: ' . $e->getMessage());
            }
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

            return true;
        }

        return false;
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

    public function getAlbums(string $baseUrl): array
    {
        $albums = [];
        $sets = $this->phpFlickr->photosets()->getList();

        if ($sets && isset($sets['photoset'])) {
            foreach ($sets['photoset'] as $i => $set) {
                $album = new Album();
                $album->fid = $set['id'];
                $album->setProvider($this->provider);
                $album->owner = $set['owner'];
                $album->title = is_string($set['title']) ? $set['title'] : $set['title']['_content'];
                $album->description = is_string($set['description']) ? $set['description'] : $set['description']['_content'];
                $album->cover = $this->getItemUrl($set);
                $album->photos = $set['photos'];
                $album->videos = $set['videos'];
                $album->sort = $i;
                $albums[] = $album;
            }
        }

        return $albums;
    }

    public function getItems(Album $album, string $baseUrl): array
    {
        $items = [];
        $extras = "date_taken, geo, tags, url_o, url_{$this->conf['vb_size']}, url_z, url_c";
        $photos = $this->phpFlickr->photosets()->getPhotos($album->fid, $album->owner, $extras);
        if ($photos && isset($photos['photo'])) {
            // calculate thumb parameters, originals are wrong in portrait
            foreach ($photos['photo'] as $i => $media) {
                $this->calculateImageSizes($media);
                $item = new Photo();
                $item->fid = $media['id'];
                $item->album = $album->id;
                $item->title = $media['title'];
                $item->type = ItemTypeEnum::IMAGE;
                if (isset($media['datetaken'])) {
                    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $media['datetaken']);
                    $item->datetaken = $datetime;
                }
                $item->url = $media['url_o'];
                $item->width = $media['width_o'];
                $item->height = $media['height_o'];
                $item->metadata = $media;
                $item->sort = $i;
                $items[] = $item;
            }
        }

        return $items;
    }

    public function albumIsDifferent(Album $album, Album $compareAlbum): bool
    {
        return $album->title !== $compareAlbum->title ||
            $album->description !== $compareAlbum->description ||
            $album->cover !== $compareAlbum->cover ||
            $album->photos !== $compareAlbum->photos ||
            $album->videos !== $compareAlbum->videos;
    }

    public function autorotate(string $albumId): void
    {
    }

    private function getItemUrl($set, $size = 'q'): string
    {
        return "https://farm{$set['farm']}.staticflickr.com/{$set['server']}/{$set['primary']}_{$set['secret']}_{$size}.jpg";
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
