<?php

namespace App\Service\Provider;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

interface ProviderInterface
{
    public function getId(): string;
    public function getLabel(): string;
    public function isEnabled(): bool;
    public function isAuthenticated(): bool;
    public function getAlbums(): array;
    public function init(): void;
    public function authenticate(Request $request, Response $response): Response;
    public function unAuthenticate(): bool;
    public function removeCache(string $album = null): bool;
    public function albumExists(string $album): bool;
    public function getMedia(string $album, string $photo): ?array;
}
