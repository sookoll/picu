<?php
namespace App\Controller;

use JsonException;
use Monolog\Logger;
use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Stream;
use Slim\Routing\RouteContext;
use Slim\Views\Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

abstract class BaseController
{
    protected array $settings;
    protected Logger $logger;
    protected PDO $db;
    protected Twig $view;

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function __construct(ContainerInterface $container)
    {
        $this->view = $container->get('view');
        $this->logger = $container->get('logger');
        $this->settings = $container->get('settings');
        $this->db = $container->get('db');
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param string $route
     * @param array $args
     * @param array $queryParams
     * @return MessageInterface|Response
     */
    protected function redirect(
        Request $request,
        Response $response,
        string $route,
        array $args = [],
        array $queryParams = []
    ): MessageInterface|Response
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor($route, $args, $queryParams);

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function render(Request $request, Response $response, string $template, array $params = []): Response
    {
        $params['user'] = $request->getAttribute('user');
        $params['base_url'] = $request->getAttribute('base_url');
        $params['title'] = $params['title'] ?? 'Picu';
        $params['version'] = $this->settings['version'];

        return $this->view->render($response, $template, $params);
    }

    /**
     * @param Response $response
     * @param mixed $data
     * @return Response
     * @throws JsonException
     */
    protected function json(Response $response, mixed $data): Response
    {
        $payload = json_encode($data, JSON_THROW_ON_ERROR);

        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json');
    }

    protected function file(Request $request, Response $response, array $source, bool $download = false): Response
    {
        $filename = $source['basename'];
        $ext = strtolower($source['extension']);
        $ctype = match ($ext) {
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpg',
            default => throw new HttpNotFoundException($request)
        };
        $stat = fstat($source['resource']);

        $newResponse = $response->withHeader('Content-type', $ctype)
            ->withHeader('Content-length', $stat['size'])
            ->withAddedHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->withHeader('Cache-Control', 'post-check=0, pre-check=0')
            ->withHeader('Pragma', 'no-cache');

        if ($download) {
            $newResponse = $newResponse
                ->withHeader('Content-Disposition', 'attachment; filename="'.$filename.'"');
        }

        return $newResponse->withBody((new Stream($source['resource'])));
    }
}
