<?php declare(strict_types=1);

namespace App\Controllers;

use Skim\Core\Response;

/**
 * Default landing page controller. #AI:class
 *
 * Serves as the starter template for new SKIM applications. Replace with
 * your own view rendering once the project has a homepage template.
 *
 * Example:
 *   // routes.php: $app->router->get('/', [HomeController::class, 'index']);
 *
 * Testing: Instantiate and call index(), assert response contains expected HTML.
 *
 * #AI:class
 */
class HomeController {
    /**
     * Returns a static HTML greeting. #AI:index
     *
     * Replace with $res->view('home/index') once templates exist.
     */
    public function index(): \Skim\Core\Response {
        return \Skim\Core\Response::html('<h1>Hello from SKIM Framework!</h1>');
    }
}

#AI:class
#AI symbol: App\Controllers\HomeController
#AI source_path: src/App/Controllers/HomeController.php
#AI title: HomeController
#AI description: Default landing page controller for new SKIM applications.
#AI role: starter controller
#AI layer: controllers
#AI badges: [controller; starter; http]
#AI intro: `HomeController` is the default landing page shipped with new SKIM projects. It returns a static HTML greeting. Replace with view rendering once the project has templates.
#AI lifecycle: instantiated per request by the router
#AI fallback: none
#AI test_seam: instantiate directly, call index(), assert response body
#AI invariants: [always returns a response object; never echoes]
#AI core_behaviors: [Returns static HTML via response::html()]
#AI warnings: []
#AI notes: This is a starter template — replace with real view rendering in production apps.
#AI scope_items: []
#AI owns: nothing
#AI entry_points: [index]
#AI config_reads: []
#AI non_goals: [Does not render templates; Does not accept request parameters]
#AI side_effects: []
#AI flow: router dispatches GET / -> HomeController::index() -> response::html(...)
#AI lifecycle_steps: [GET / request; -> router matches HomeController::index; -> response::html() returned; -> middleware pipeline processes response]
#AI section_order: [Actions]
#AI architectural_notes: Minimal controller demonstrating the SKIM controller contract — always return a response, never echo.

#AI:index
#AI group: Actions
#AI frequency: high
#AI signature: public function index(): response
#AI contract: Returns a static HTML greeting response. Replace with view rendering for production use.
#AI return_detail: {type: response | desc: HTML response with greeting content.}
