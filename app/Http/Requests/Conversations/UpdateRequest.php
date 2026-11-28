<?php

namespace App\Http\Requests\Conversations;

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
        $conversation=$this->route('conversation');
        
        return [
            'title'=>[
                'required','string','max:255',
                Rule::unique('conversations','title')
                ->where('project_id',$conversation->project_id)
                ->ignore($conversation->id)
                ],
        ];
    }
}
