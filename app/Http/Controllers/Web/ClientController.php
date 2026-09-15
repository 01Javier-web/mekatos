<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\JuiceOptions;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function table(string $token): View
    {
        return view('client.menu-v2', [
            'token' => $token,
            'juiceFruits' => JuiceOptions::availableFruits(),
        ]);
    }
}
