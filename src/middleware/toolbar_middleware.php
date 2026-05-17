<?php declare(strict_types=1);

namespace skim\middleware;

use skim\core\middleware;
use skim\core\request;
use skim\core\response;
use skim\dev\toolbar;

// Appends the debug toolbar HTML before </body> in text/html responses.
// Active only when APP_DEBUG=true AND response is HTML AND not a JSON/AJAX request.
// Why middleware and not response::send(): allows toolbar to read profiler after
// the controller runs — controller must finish first so all queries are recorded.
class toolbar_middleware implements middleware {
    public function handle(request $req, response $res, callable $next): mixed {
        $result = $next($req, $res);

        if (!($result instanceof response)) {
            return $result;
        }

        if (!\skim\core\config::get('app.debug', false)) {
            return $result;
        }

        if ($req->is_json() || $req->is_ajax() || $req->is_htmx()) {
            return $result;
        }

        $ct = $result->get_headers()['Content-Type'] ?? '';
        if (!str_contains($ct, 'text/html')) {
            return $result;
        }

        $html = $result->get_body();
        if (str_contains($html, '</body>')) {
            $bar  = toolbar::render($req);
            $html = str_replace('</body>', $bar . '</body>', $html);
            $result->set_body($html)->with_header('X-Debug', 'toolbar-injected');
        }

        return $result;
    }
}
