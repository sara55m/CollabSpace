<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ConversationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type'=>['nullable',Rule::enum(ConversationType::class)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
