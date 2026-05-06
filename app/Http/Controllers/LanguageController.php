<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function switch(Request $request)
{
    $locale = $request->route('locale');
    $supported = ['ru','en','es','de','fr','it','pt','ar','zh','ja','ko','tr','hi'];
    if (in_array($locale, $supported)) {
        Session::put('locale', $locale);
        App::setLocale($locale);
    }
    return redirect()->back();
}
}