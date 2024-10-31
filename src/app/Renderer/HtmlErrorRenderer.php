<?php
namespace App\Renderer;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Slim\Interfaces\ErrorRendererInterface;
use Slim\Views\Twig;
use Twig\Error\LoaderError;

class HtmlErrorRenderer implements ErrorRendererInterface
{
    protected Twig $view;
    protected array $settings;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws LoaderError
     */
    public function __construct(ContainerInterface $container)
    {
        $this->settings = $container->get('settings');
        $this->view = Twig::create($this->settings['view']['template_path'], $this->settings['view']['twig']);
    }

    public function __invoke(\Throwable $exception, bool $displayErrorDetails): string
    {
        if ($exception->getCode() === 404) {
            return $this->view->fetch('error/404.twig', [
                'base_url' => $this->settings['basePath']
            ]);
        }

        $title = '500 - ' .  get_class($exception);
        if (is_a($exception, '\Slim\Exception\HttpException')) {
            $title = $exception->getTitle();
        }

        return $this->view->fetch('error/default.twig', [
            'base_url' => $this->settings['basePath'],
            'title' => $title,
            'debug' => $displayErrorDetails,
            'type' => get_class($exception),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' =>  $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
