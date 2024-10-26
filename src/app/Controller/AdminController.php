<?php
namespace App\Controller;

use App\Enum\ProviderEnum;
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
    public function __construct(ContainerInterface $container, protected readonly ProviderService $service)
    {
        parent::__construct($container);
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
        foreach ($this->service->getProviders() as $key => $provider) {
            if ($provider->isEnabled()) {
                $providers[$key] = [
                    'page' => 'admin',
                    'id' => $provider->getId(),
                    'label' => $provider->getLabel(),
                    'authenticated' => $provider->isAuthenticated(),
                    'albums' => $this->service->getAlbumService()->getList($provider),
                ];
            }
        }

        return $this->render($request, $response, 'admin/admin.twig', ['providers' => $providers]);
    }


    public function login(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        if ($provider->isEnabled() && !$provider->isAuthenticated()) {
            return $this->service->getProviderApiService($providerEnum)->authenticate($request, $response);
        }

        return $response->withStatus(400);
    }

    public function logout(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated() &&
            $this->service->getProviderApiService($providerEnum)->unAuthenticate()
        ) {
            return Utilities::redirect('admin', $request, $response);
        }

        return $response->withStatus(400);
    }

    public function validate(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $data = [];
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $this->service->setBaseUrl($request->getAttribute('base_url'));
            $data = [
                'provider' => [
                    'page' => 'admin',
                    'id' => $provider->getId(),
                    'label' => $provider->getLabel(),
                    'authenticated' => $provider->isAuthenticated(),
                    'editable' => $provider->isEditable(),
                    'albums' => $this->service->diff($providerEnum)
                ]
            ];
        }

        return $this->render($request, $response, 'admin/import.twig', $data);
    }

    public function import(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $album = $args['album'] ?? null;
        if (
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $this->service->setBaseUrl($request->getAttribute('base_url'));
            if ($this->service->sync($providerEnum, $album)) {
                return Utilities::redirect('import_validate', $request, $response, ['provider' => $provider->getId()]);
            }
        }

        return $response->withStatus(400);
    }

    public function autorotate(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $album = $args['album'] ?? null;
        if (
            $album &&
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $this->service->setBaseUrl($request->getAttribute('base_url'));
            if ($this->service->autorotate($providerEnum, $album)) {
                return Utilities::redirect('import_validate', $request, $response, ['provider' => $provider->getId()]);
            }
        }

        return $response->withStatus(400);
    }

    public function upload(Request $request, Response $response, array $args = []): Response
    {
        return $response->withStatus(400);
    }

    public function delete(Request $request, Response $response, array $args = []): Response
    {
        $providerEnum = ProviderEnum::from($args['provider']);
        $provider = $this->service->getProvider($providerEnum);
        $albumId = $args['album'] ?? null;
        if (
            $albumId &&
            $provider->isEnabled() &&
            $provider->isAuthenticated()
        ) {
            $album = $this->service->getAlbumService()->get($provider, $albumId);
            if ($album) {
                $this->service->getAlbumService()->clear($album);
                $this->service->getAlbumService()->delete($album);

                return $response->withStatus(204);
            }
        }

        return $response->withStatus(400);
    }
}
