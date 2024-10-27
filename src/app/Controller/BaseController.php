<?php
namespace App\Controller;

use JsonException;
use Monolog\Logger;
use PDO;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
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
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    protected function render(Request $request, Response $response, string $template, array $params = []): Response
    {
        $params['user'] = $request->getAttribute('user');
        $params['base_url'] = $request->getAttribute('base_url');
        $params['title'] = $params['title'] ?? 'Picu';

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
}
