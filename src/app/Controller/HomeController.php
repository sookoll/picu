<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

final class HomeController extends BaseController
{
    public function index(Request $request, Response $response): Response
    {
        throw new HttpNotFoundException($request);
    }

}
