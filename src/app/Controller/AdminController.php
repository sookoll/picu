<?php
namespace App\Controller;

use App\Service\Provider\ProviderInterface;
use App\Service\ProviderService;
use App\Service\Utilities;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

final class AdminController extends BaseController
{
    public function __construct(ContainerInterface $container, private readonly ProviderService $service)
    {
        parent::__construct($container);
        $this->service->ensureDirectoriesExists($this->settings);
        $this->service->initProviders($this->settings['providers'] ?? null, true);
    }

    public function index(Request $request, Response $response, array $args = []): Response
    {
        // enabled service providers
        $providers = [];
        /** @var $provider ProviderInterface */
        foreach ($this->service->getProviders() as $key => $provider) {
            if ($provider->isEnabled()) {
                $providers[$key] = [
                    'page' => 'admin',
                    'label' => $provider->getLabel(),
                    'authenticated' => $provider->isAuthenticated(),
                    'albums' => $provider->getAlbums(),
                ];
            }
        }

        return $this->render($request, $response, 'admin/admin.twig', ['providers' => $providers]);
    }


    public function login(Request $request, Response $response, array $args = []): Response
    {
        /** @var ProviderInterface $provider */
        $provider = $this->service->getProvider($args['provider']);
        if ($provider && $provider->isEnabled() && !$provider->isAuthenticated()) {
            return $provider->authenticate($request, $response);
        }

        return $response->withStatus(400);
    }

    public function logout(Request $request, Response $response, array $args = []): Response
    {
        /** @var ProviderInterface $provider */
        $provider = $this->service->getProvider($args['provider']);
        if ($provider && $provider->isEnabled() && $provider->isAuthenticated() && $provider->unAuthenticate()) {
            return Utilities::redirect('admin', $request, $response);
        }

        return $response->withStatus(400);
    }

    public function removeCache(Request $request, Response $response, array $args = []): Response
    {
        /** @var ProviderInterface $provider */
        $provider = $this->service->getProvider($args['provider']);
        $album = $args['album'] ?? null;
        if ($provider && $provider->isEnabled() && $provider->removeCache($album)) {
            return Utilities::redirect('admin', $request, $response);
        }

        return $response->withStatus(400);
    }

    public function upload(Request $request, Response $response, array $args = []): Response
    {
        return $response->withStatus(400);
    }
}
