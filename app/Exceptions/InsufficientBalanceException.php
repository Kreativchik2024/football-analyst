<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

class InsufficientBalanceException extends Exception
{
    protected $message = 'Недостаточно средств на счёте.';

    public function render(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => $this->message,
            ], 422);
        }

        return response()->view('errors.insufficient-balance', [
            'error' => $this->message,
        ], 422);
    }
}
