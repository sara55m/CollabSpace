<?php

namespace App\Http\Requests\Projects;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project=$this->route('project');

        // the start date can't be after the deadline, even if only one of them is sent
        $deadline  = $this->has('deadline')
            ? $this->input('deadline')
            : $project->deadline?->toDateString();

        // the deadline can't be before the start date, even if only one of them is sent
        $startDate = $this->has('start_date')
            ? $this->input('start_date')
            : $project->start_date?->toDateString();

        return [
            'name'=>['sometimes','required','string','max:255'],
            'priority'=>['sometimes','required',Rule::enum(ProjectPriority::class)],
            'start_date' => array_filter([
                'sometimes', 'required', 'date',
                $deadline ? "before_or_equal:{$deadline}" : null,
            ]),
            'deadline' => array_filter([
                'sometimes', 'nullable', 'date',
                $startDate ? "after_or_equal:{$startDate}" : null,
            ]),
            'description' => ['nullable', 'string', 'max:5000'],
            'type'   => ['sometimes', 'required', 'string', 'max:100'],
            'status' => ['sometimes', 'required', Rule::enum(ProjectStatus::class)],

            'members'=>['sometimes', 'array'],
            'members.*'=>['distinct','integer','exists:users,id'],

            'attachments'    => ['sometimes', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],

            //remove attachment ids must belong to the project
            'remove_attachments'   => ['sometimes', 'array'],
            'remove_attachments.*' => [
                'integer', 'distinct',
                Rule::exists('attachments', 'id')
                    ->where('attachable_type', $project->getMorphClass())
                    ->where('attachable_id', $project->id),
            ],
        ];
    }
}
