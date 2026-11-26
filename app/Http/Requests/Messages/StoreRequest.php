<?php

namespace App\Http\Requests\Messages;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
            // a message needs a body, attachments, or both
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachments'],

            'attachments'   => ['nullable', 'array', 'max:10', 'required_without:body'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,txt,zip',
            ],
        ];
    }
}
