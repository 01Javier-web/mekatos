<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function table(string $token): View
    {
        return view('client.menu', compact('token'));
    }
}
