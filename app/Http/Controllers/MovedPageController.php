<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The old site's addresses (plan Phase 12): sends the player to the page that replaced them, carrying the query string
 * on (or jumping to a tab). The target comes from the route's defaults, never a closure: production caches its routes,
 * and a closure built by another closure loses its argument there (Sentry KADI-F).
 */
class MovedPageController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $route = $request->route();
        $url = route($route->defaults['to']);

        if (isset($route->defaults['tab'])) {
            return redirect()->to($url.'#'.$route->defaults['tab']);
        }

        $query = $request->getQueryString();

        return redirect()->to($url.($query ? '?'.$query : ''));
    }
}
