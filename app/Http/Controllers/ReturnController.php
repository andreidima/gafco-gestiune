<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class ReturnController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('transfers.index', ['purpose' => 'return']);
    }
}
