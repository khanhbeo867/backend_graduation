<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    use \App\Support\Concerns\ApiResponse;

    //test ci/cd
    protected function getUserName(): string
    {
        return Auth::user()->username;
    }
}
