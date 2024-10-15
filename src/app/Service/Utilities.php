<?php

namespace App\Service;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

class Utilities
{
    public static function redirect(string $path, Request $request, Response $response): Response
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor($path);

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    public static function download(string $url, string $referer)
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $handle = fopen('php://temp', 'rb+');
        if (!$handle){
            return null;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FILE, $handle);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MozillaXYZ/1.0');
        // Include header in result? (0 = yes, 1 = no)
        curl_setopt($ch, CURLOPT_HEADER, 0);
        // Should cURL return or print out the data? (true = return, false = print)
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Timeout in seconds
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        // Download the given URL
        curl_exec($ch);
        // get file size
        //$fileSize = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        // Close the cURL resource, and free system resources
        curl_close($ch);

        return $handle;

        //return [$output, $fileSize];

    }
}
