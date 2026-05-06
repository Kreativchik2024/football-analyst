<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    protected array $supported = [
        'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'ar', 'zh', 'ja', 'ko', 'tr', 'hi'
    ];

    public function handle(Request $request, Closure $next)
    {
        if (Session::has('locale') && in_array(Session::get('locale'), $this->supported)) {
            App::setLocale(Session::get('locale'));
        } else {
            $browserLocale = substr($request->server('HTTP_ACCEPT_LANGUAGE', 'en'), 0, 2);
            App::setLocale(in_array($browserLocale, $this->supported) ? $browserLocale : 'ru');
        }
        return $next($request);
    }
}