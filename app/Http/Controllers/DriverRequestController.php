<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class DriverRequestController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('tasks.index');
    }
}
