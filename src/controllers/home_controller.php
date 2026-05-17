<?php declare(strict_types=1);

namespace app\controllers;

use skim\core\response;

class home_controller {
    public function index(): response {
        return response::html('<h1>Hello from SKIM Framework!</h1>');
    }
}
