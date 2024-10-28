<?php

namespace App\Service\Api;

use App\Enum\ItemSizeEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\Provider;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

interface ApiInterface
{
    public function setProvider(Provider $provider): void;
    public function init(): bool;
    public function authenticate(Request $request, Response $response): Response;
    public function unAuthenticate(): bool;
    //public function syncCache(string $album = null): bool;
    //public function removeCache(string $album = null): bool;
    //public function albumExists(string $album): bool;
    public function getAlbums(): array;
    public function getItems(Album $album): array;
    public function albumIsDifferent(Album $album, Album $compareAlbum): bool;
    public function autorotate(string $albumId): void;
    public function getItemsFidList(Album $album): array;
    public function readFile(Album $album, Photo $item, ItemSizeEnum $sizeEnum = null): array;
}