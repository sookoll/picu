<?php

namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;

final class AuthController extends BaseController
{
    public function login(Request $request, Response $response, array $args = []): Response
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        if ($request->getMethod() === 'POST') {
            $data = $request->getParsedBody();

            if (empty($data['user_id']) || empty($data['user_pwd'])) {
                $url = $routeParser->urlFor('login');

                return $response->withStatus(302)->withHeader('Location', $url);
            }

            // Check the user username / pass
            if ($this->auth($data['user_id'], $data['user_pwd'])) {
                $session = $request->getAttribute('session');
                $session['logged'] = true;
                $session['user'] = [
                    'username' => $data['user_id'],
                    'is_admin' => true,
                ];
                $url = $routeParser->urlFor('admin');

                return $response->withStatus(302)->withHeader('Location', $url);
            }

            $url = $routeParser->urlFor('login');

            return $response->withStatus(302)->withHeader('Location', '/admin/login');
        }
        return $this->render($request, $response, 'admin/login.twig');
    }

    public function logout(Request $request, Response $response, array $args = []): Response
    {
        $routeParser = RouteContext::fromRequest($request)->getRouteParser();

        $session = $request->getAttribute('session');
        $session['logged'] = false;
        unset($session['user']);

        $url = $routeParser->urlFor('admin');

        return $response->withStatus(302)->withHeader('Location', $url);
    }

    public function hash(Request $request, Response $response, array $args = []): Response
    {
        $response->getBody()->write(password_hash($args['pwd'], PASSWORD_BCRYPT));

        return $response->withHeader('Content-Type', 'text/plain');
    }

    private function auth(string $uname, string $pswd)
    {
        return $uname === $this->settings['admin_user'] && password_verify($pswd, $this->settings['admin_pass']);
    }
}
