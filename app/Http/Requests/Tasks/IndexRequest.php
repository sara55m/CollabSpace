<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

class IndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //if the request is project tasks make sure the user is authorized to view the project
        $project=$this->route('project');
        return $project ? $this->user()->can('view',$project) : true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search'     => ['nullable', 'string', 'max:100'],
            'status'     => ['nullable', Rule::enum(TaskStatus::class)],
            'priority'   => ['nullable', Rule::enum(TaskPriority::class)],
            'team_id'    => ['nullable', 'integer'],
            'per_page'   => ['nullable', 'integer', 'between:1,100'],

            // "my tasks" only: narrow to one project the user belongs to
            'project_id' => [
                'nullable', 'integer',
                Rule::exists('project_user', 'project_id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
