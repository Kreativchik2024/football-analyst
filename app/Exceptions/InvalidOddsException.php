<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InvalidOddsException extends Exception
{
    protected $message = 'Коэффициент для выбранного исхода недоступен.';

    public function render(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => $this->message,
            ], 422);
        }

        return response()->view('errors.invalid-odds', [
            'error' => $this->message,
        ], 422);
    }
}
