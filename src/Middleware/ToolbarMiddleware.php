<?php declare(strict_types=1);

namespace Skim\Middleware;

use Skim\Core\Middleware;
use Skim\Core\Request;
use Skim\Core\Response;
use Skim\Dev\Toolbar;

/**
 * Injects the debug toolbar HTML before </body> in HTML responses.
 *
 * Use as a global middleware during development. Active only when
 * APP_DEBUG=true AND the response is text/html AND the request is
 * not JSON, AJAX, or hypermedia (htmx, datastar, turbo). Runs after the controller so profiler
 * data is complete.
 *
 * Example:
 *   // Registered globally in app boot — no manual setup needed
 *   $app->use(ToolbarMiddleware::class);
 *
 * #AI:class
 */
class ToolbarMiddleware implements Middleware {
    /**
     * Injects toolbar HTML into HTML responses when debug mode is active. #AI:handle
     *
     * Passes the request through to $next first, then checks: debug config,
     * request type (skips JSON/AJAX/hypermedia), and Content-Type (must be text/html).
     * Replaces </body> with toolbar HTML + </body>.
     *
     * @param Request  $req  Current HTTP request.
     * @param Response $res  Current HTTP response.
     * @param callable $next Next middleware or controller in the pipeline.
     */
    public function handle(Request $req, Response $res, callable $next): mixed {
        $result = $next($req, $res);

        if (!($result instanceof Response)) {
            return $result;
        }

        if (!\Skim\Core\Config::get('app.debug', false)) {
            return $result;
        }

        if ($req->isJson() || $req->isAjax() || $req->isHypermedia()) {
            return $result;
        }

        $ct = $result->getHeaders()['Content-Type'] ?? '';
        if (!str_contains($ct, 'text/html')) {
            return $result;
        }

        $html = $result->getBody();
        if (str_contains($html, '</body>')) {
            $bar  = Toolbar::render($req);
            $html = str_replace('</body>', $bar . '</body>', $html);
            $result->setBody($html)->withHeader('X-Debug', 'toolbar-injected');
        }

        return $result;
    }
}

#AI:class
#AI symbol: Skim\Middleware\ToolbarMiddleware
#AI source_path: src/Middleware/ToolbarMiddleware.php
#AI title: toolbar_middleware
#AI description: Injects the debug toolbar HTML into text/html responses when APP_DEBUG is true.
#AI role: debug toolbar middleware
#AI layer: middleware
#AI badges: [middleware; debug; toolbar; dev-only]
#AI intro: `ToolbarMiddleware` appends the SKIM debug toolbar before `</body>` in HTML responses. It only activates when debug mode is enabled and the request is a standard page load (not JSON, AJAX, or hypermedia).
#AI lifecycle: registered as global middleware; runs after controller on every request
#AI test_seam: set app.debug to false to disable; mock Toolbar::render() for output testing
#AI invariants: [Only modifies text/html responses; Skips JSON/AJAX/hypermedia requests; No-op when debug is false]
#AI core_behaviors: [Calls $next first to collect profiler data; Checks debug flag, request type, and content type; Injects toolbar HTML before </body>]
#AI owns: nothing
#AI entry_points: [handle]
#AI config_reads: [app.debug]
#AI non_goals: [Does not render toolbar for API responses; Does not modify non-HTML content; Does not collect profiler data itself]
#AI side_effects: [Modifies response body by injecting toolbar HTML; Adds X-Debug header]
#AI flow: handle() -> $next() -> check debug -> check request type -> check content-type -> Toolbar::render() -> inject before </body>
#AI section_order: [Middleware]

#AI:handle
#AI group: Middleware
#AI frequency: high
#AI signature: public function handle(Request $req, Response $res, callable $next): mixed
#AI contract: Runs the next middleware/controller, then injects toolbar HTML into the response if all conditions are met: debug enabled, non-API request, text/html content type, and </body> present.
#AI param_details: [{name: $req | type: request | required: true | desc: Current HTTP request.}; {name: $res | type: response | required: true | desc: Current HTTP response.}; {name: $next | type: callable | required: true | desc: Next middleware or controller in the pipeline.}]
#AI return_detail: {type: mixed | desc: The response, possibly with toolbar HTML injected.}
#AI side_effects: [Modifies response body; Adds X-Debug response header]
