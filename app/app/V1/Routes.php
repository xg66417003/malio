<?php


namespace App\V1;


use App\V1\Controllers\BaseController;
use App\V1\Middleware\V1Check;
use Slim\App;

class Routes
{
    public static function loadRoutes(App &$app)
    {
        $app->group('/v1', function () {
            $this->post('/login',BaseController::class .':login');
            $this->get('/link',BaseController::class.':sublink');
            $this->get('/userinfo',BaseController::class.':userinfo');
            $this->get('/init',BaseController::class.':init');
            $this->get('/broadcast',BaseController::class.':broadcast');
            $this->get('/update',BaseController::class.':update');
            $this->get('/downloadClash',BaseController::class.':downloadClash');
            $this->get('/logout',BaseController::class.':logout');
            $this->get('/anno',BaseController::class.':anno');
            $this->get('/pc-alert',BaseController::class.':pcAlert');
            $this->get('/pc-update',BaseController::class.':pcUpdateCheck');
            $this->get('/config',BaseController::class.':config');
            $this->get('/online',BaseController::class.':online');
        })->add(new V1Check());
    }
}
