<?php


namespace App\V1\Middleware;


use App\Services\Auth as AuthService;
use App\V1\ResponseFormat;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Http\Stream;

class V1Check
{

    public $notNeedLogin=[
        '/v1/login',
        '/v1/init',
        '/v1/broadcast',
        '/v1/update',
        '/v1/config'
    ];

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if (in_array($_SERVER['REQUEST_URI'],$this->notNeedLogin)){
            return $next($request, $response);
        }
        $user = AuthService::getUser();
        if (!$user->isLogin) {
            $response->getBody()->write(ResponseFormat::unAuth());
            $newResponse = $response->withStatus(200);
            return $newResponse;
        }
        if ($user->enable == 0) {
            $response->getBody()->write(ResponseFormat::forbid());
            $newResponse = $response->withStatus(200);
            return $newResponse;
        }
        $response = $next($request, $response);
        return $response;
    }
}
