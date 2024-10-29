<?php

namespace App\Service;

use PDO;
use PDOException;
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

    private function idExist(string $table, string $id): bool
    {
        $sql = "
            SELECT id FROM $table WHERE id = :id
        ";
        $params = [
            'id' => $id,
        ];
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('ID query failed: ' . $e->getMessage());
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return (bool)$row;
    }

    protected function ensureUniqueId(string $table, string $id = null): string
    {
        while(!$id || $this->idExist($table, $id)) {
            $id = Utilities::uid();
        }

        return $id;
    }
}
