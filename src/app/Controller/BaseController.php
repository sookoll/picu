<?php
namespace App\Controller;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class BaseController
{
    protected $view;
    protected $logger;
    protected array $settings;

    public function __construct(ContainerInterface $container)
    {
        $this->view = $container->get('view');
        $this->logger = $container->get('logger');
        $this->settings = $container->get('settings');
    }

    protected function render(Request $request, Response $response, string $template, array $params = []): Response
    {
        $params['user'] = $request->getAttribute('user');
        $params['base_url'] = $request->getAttribute('base_url');
        $params['title'] = $params['title'] ?? 'Picu';

        return $this->view->render($response, $template, $params);
    }
}
