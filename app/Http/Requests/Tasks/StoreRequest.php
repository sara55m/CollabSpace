<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TaskPriority;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //only the project members can create tasks
        return $this->user()->can('createTask',$this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        //if a team is selected then assignees must be members of this team ,else assignees must be members of the project
        $assigneeExists = $this->filled('team_id')
        ? Rule::exists('team_user', 'user_id')->where('team_id', $this->input('team_id'))
        : Rule::exists('project_user', 'user_id')->where('project_id', $project->id);

        return [
            'title'      => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'due_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority'=>['nullable',Rule::enum(TaskPriority::class)],
            'description' => ['nullable', 'string'],

            'team_id'=>['nullable','integer',Rule::exists('teams','id')->where('project_id',$project->id)],

            'assignees'   => ['required', 'array','min:1'],

            'assignees.*' =>['integer', 'distinct',$assigneeExists],

            'reviewer_id'=>['nullable','integer',Rule::exists('project_user', 'user_id')->where('project_id', $project->id),],

            'attachments'   => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],
        ];
    }
}
