<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
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
            'name'            => ['nullable', 'string', 'max:255'],
            'status'          => ['nullable', Rule::enum(ProjectStatus::class)],      // use your real values
            'priority'        => ['nullable', Rule::enum(ProjectPriority::class)],
            'start_date_from' => ['nullable', 'date'],
            'start_date_to'   => ['nullable', 'date', 'after_or_equal:start_date_from'],
            'deadline_from'   => ['nullable', 'date'],
            'deadline_to'     => ['nullable', 'date', 'after_or_equal:deadline_from'],
            'sort'            => ['nullable', Rule::in(['newest', 'oldest'])],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
