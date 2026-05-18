<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function index()
    {
        // Можно передать дополнительные данные в представление
        $data = [
            'pageTitle' => 'О проекте | AI-прогнозы',
            'metaDescription' => 'Проект по машинному обучению для футбольных прогнозов. Развивается одним человеком на арендованных мощностях. Будем рады поддержке!',
        ];
        
        return view('about', $data);
    }
}