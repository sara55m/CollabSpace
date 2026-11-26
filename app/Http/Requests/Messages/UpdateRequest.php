<?php

namespace App\Http\Requests\Messages;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
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
        $message = $this->route('message');

        return [
            // a text-only message can't be emptied; one with attachments can
            'body' => [
                Rule::requiredIf(fn () => ! $message->attachments()->exists()),
                'nullable',
                'string',
                'max:5000',
            ],
        ];
    }
}
