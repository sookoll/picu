<?php
namespace App\Controller;

use App\Enum\ProviderEnum;
use App\Model\Album;
use App\Service\ProviderService;
use App\Service\Utilities;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class AdminController extends BaseController
{
    public function __construct(
        ContainerInterface $container,
        protected readonly ProviderService $service,
    )
    {
        parent::__construct($container);
        set_time_limit(60);
        Utilities::ensureDirectoriesExists($this->settings);
        $this->service->initProviders();
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function index(Request $request, Response $response, array $args = []): Response
    {
        // enabled service providers
        $providers = [];
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        foreach ($this->service->getProviders() as $key => $provider) {
            if ($provider->isEnabled()) {
                $providers[$key] = [
                    'id' => $provider->getId(),
                    'label' => $provider->getLabel(),
                    'authenticated' => $provider->isAuthenticated(),
                    'editable' => $provider->isEditable(),
                    'albums' => $this->service->getAlbumService()->getList($provider),
                ];
            }
        }

        return $this->render($request, $response, 'admin/admin.twig', [
            'page' => 'admin',
            'token' => $this->settings['api_token'],
            'providers' => $providers
        ]);
    }


    public function login(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        if ($provider->isEnabled() && !$provider->isAuthenticated()) {
            return $this->service->getProviderApiService($providerEnum)?->authenticate($request, $response);
        }

        return $response->withStatus(400);
    }

    public function logout(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated() &&
            $this->service->getProviderApiService($providerEnum)?->unAuthenticate()
        ) {
            return Utilities::redirect('admin', $request, $response);
        }

        return $response->withStatus(400);
    }

    public function validate(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $data = [];
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $data = [
                'page' => 'admin',
                'provider' => [
                    'id' => $provider->getId(),
                    'label' => $provider->getLabel(),
                    'authenticated' => $provider->isAuthenticated(),
                    'editable' => $provider->isEditable()
                ],
                'albums' => $this->service->diff($providerEnum)
            ];
        }

        return $this->render($request, $response, 'admin/import.twig', $data);
    }

    public function import(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $albumFid = $args['album'] ?? null;
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated() &&
            $albumFid
        ) {
            if ($this->service->sync($providerEnum, $albumFid)) {
                $result = [];
                /** @var Album $album */
                foreach($this->service->diff($providerEnum, $albumFid) as $album) {
                    $result[] = [
                        'id' => $album->id,
                        'fid' => $album->fid,
                        'photos' => $album->photos,
                        'videos' => $album->videos,
                        'status' => $album->getStatus()
                    ];
                }

                return $this->json($response, $result);
            }
        }

        return $response->withStatus(404);
    }

    public function upload(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        return $response->withStatus(400);
    }

    public function album(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $albumId = $args['album'] ?? null;
        $data = [];
        if (
            $albumId &&
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $album = $this->service->getAlbumService()->get($albumId);
            if ($album) {
                $items = $this->service->getItemService()->getList($album);
                $data = [
                    'page' => 'admin',
                    'provider' => [
                        'id' => $provider->getId(),
                        'label' => $provider->getLabel(),
                        'authenticated' => $provider->isAuthenticated(),
                        'editable' => $provider->isEditable(),
                    ],
                    'album' => $album,
                    'items' => $items
                ];
            }
        }

        return $this->render($request, $response, 'admin/album.twig', $data);
    }

    public function updateAlbum(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $albumId = $args['album'] ?? null;
        if (
            $albumId &&
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $album = $this->service->getAlbumService()->get($albumId);
            if ($album) {
                $data = $request->getParsedBody();
                foreach ($data as $key => $val) {
                    if (property_exists($album, $key)) {
                        switch ($key) {
                            case 'public':
                                $album->{$key} = $val === 'true';
                                break;
                            default:
                                $album->{$key} = $val;
                        }
                    }
                }
                $this->service->getAlbumService()->update($album);

                return $response->withStatus(204);
            }
        }

        return $response->withStatus(400);
    }

    public function delete(Request $request, Response $response, array $args = []): Response
    {
        $this->service->setBaseUrl($request->getAttribute('base_url'));
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $albumId = $args['album'] ?? null;
        if (
            $albumId &&
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $album = $this->service->getAlbumService()->get($albumId);
            if ($album) {
                $this->service->getProviderApiService($providerEnum)?->clearCache($album);
                $this->service->getItemService()->deleteAll($album);
                $this->service->getAlbumService()->delete($album);

                return $response->withStatus(204);
            }
        }

        return $response->withStatus(400);
    }
}
