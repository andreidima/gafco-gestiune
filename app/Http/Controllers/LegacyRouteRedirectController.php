<?php

namespace App\Http\Controllers;

use App\Support\RomanianUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyRouteRedirectController extends Controller
{
    public function __invoke(Request $request, RomanianUrl $romanianUrl): RedirectResponse
    {
        $source = $request->getRequestUri();
        $target = $romanianUrl->translate($source);

        abort_if($target === $source, 404);

        return redirect()->to($target);
    }
}
