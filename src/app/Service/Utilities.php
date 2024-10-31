<?php

namespace App\Service;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Slim\Routing\RouteContext;

class Utilities
{
    public static function redirect(string $path, Request $request, Response $response, array $data = []): Response
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();
        $url = $routeParser->urlFor($path, $data);

        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    /**
     * @param array $settings
     * @return void
     */
    public static function ensureDirectoriesExists(array $settings): void
    {
        self::ensureDirectoryExists($settings['cacheDir']);
        self::ensureDirectoryExists($settings['tokenDir']);
    }

    public static function deleteDir($path): bool
    {
        if (!file_exists($path) || empty($path)) {
            return true;
        }
        $class_func = array(__CLASS__, __FUNCTION__);

        return is_file($path) ?
            @unlink($path) :
            array_map($class_func, glob($path.'/*')) === @rmdir($path);
    }

    public static function safeFn(string $path, string $file): string
    {
        if (!file_exists("$path/$file")) {
            return $file;
        }
        if (is_dir("$path/$file")) {
            $file_name_str = $file;
            $file_ext = '';
        }
        else {
            $file_name_str = pathinfo($file, PATHINFO_FILENAME);
            $file_ext = pathinfo($file, PATHINFO_EXTENSION);
            if ($file_ext !== '') {
                $file_ext = '.' . $file_ext;
            }
        }

        // Replaces all spaces with hyphens.
        $file_name_str = str_replace(' ', '-', $file_name_str);
        // Removes special chars.
        $file_name_str = preg_replace('/[^A-Za-z0-9\-]/', '', $file_name_str);
        // Replaces multiple hyphens with single one.
        $file_name_str = preg_replace('/-+/', '-', $file_name_str);

        if (($file_name_str . $file_ext) === $file) {
            return $file;
        }

        $i = 0;
        while(file_exists("$path/$file_name_str" . $file_ext)) {
            $i++;
            $file_name_str .= $i;
        }

        rename("$path/$file", "$path/$file_name_str" . $file_ext);

        return $file_name_str . $file_ext;
    }

    public static function serializeStatementCondition(array $conditions, $operator = 'AND'): string
    {
        $conditionsString = array_map(function($key) use ($conditions) {
            return "{$key} {$conditions[$key][0]} {$conditions[$key][1]}";
        }, array_keys($conditions));

        return implode(" {$operator} ", $conditionsString);
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

    public static function findObjectBy($arr, $key, $value)
    {
        foreach ($arr as $element) {
            if ($value === $element->{$key}) {
                return $element;
            }
        }

        return null;
    }

    public static function uid(): string
    {
        $input = microtime();
        $length = 8;
        // Create a raw binary sha256 hash and base64 encode it.
        $hash_base64 = base64_encode(hash('sha256', $input, true));
        // Replace non-urlsafe chars to make the string urlsafe.
        $hash = strtr($hash_base64, '+/_', '---');
        $hash = str_replace('-', '', $hash);
        // Trim base64 padding characters from the end.
        $hash = rtrim($hash, '=');
        $hash = ltrim($hash, '0123456789');

        // Shorten the string before returning.
        return substr( $hash, 0, $length );
    }

    public static function ensureDirectoryExists(string $path): void
    {
        if (!file_exists($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $path));
        }
    }

}
