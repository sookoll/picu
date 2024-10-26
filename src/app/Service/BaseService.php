<?php

namespace App\Service;

use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class BaseService
{
    protected array $settings;
    protected LoggerInterface $logger;
    protected PDO $db;
    protected string $baseUrl;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get('logger');
        $this->settings = $container->get('settings');
        $this->db = $container->get('db');
    }

    /**
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setDb(PDO $db): void
    {
        $this->db = $db;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
    }
}
