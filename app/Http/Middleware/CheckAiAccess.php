<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckAiAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && Auth::user()->canAccessAiPredictions()) {
            return $next($request);
        }

        return redirect()->route('login')->with('error', 'Доступ запрещён.');
    }
}