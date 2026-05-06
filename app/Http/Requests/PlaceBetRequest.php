<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'fixture_id' => 'required|integer|exists:fixtures,id',
            'market'     => 'required|string|in:1x2,over_under_2.5,btts',
            'outcome'    => 'required|string',
            'stake'      => 'required|numeric|min:1|max:100000',
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'fixture_id.required' => 'Матч обязателен',
            'fixture_id.exists' => 'Выбранный матч не найден',
            'market.required' => 'Рынок обязателен',
            'market.in' => 'Выбранный рынок недоступен',
            'outcome.required' => 'Исход обязателен',
            'stake.required' => 'Сумма ставки обязательна',
            'stake.numeric' => 'Сумма должна быть числом',
            'stake.min' => 'Минимальная сумма ставки: 1',
            'stake.max' => 'Максимальная сумма ставки: 100000',
        ];
    }
}
