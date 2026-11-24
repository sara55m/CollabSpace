<?php

namespace App\Http\Requests\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
        return $this->user()->can('update',$this->route('task'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $task = $this->route('task');

        $teamId = $this->has('team_id') ? $this->input('team_id') : $task->team_id;

        $assigneeExists = $teamId
            ? Rule::exists('team_user', 'user_id')->where('team_id', $teamId)
            : Rule::exists('project_user', 'user_id')->where('project_id', $task->project_id);

        // the start date can't be after the due_date, even if only one of them is sent
        $dueDate  = $this->has('due_date')
            ? $this->input('due_date')
            : $task->due_date?->toDateString();

        // the due_date can't be before the start date, even if only one of them is sent
        $startDate = $this->has('start_date')
            ? $this->input('start_date')
            : $task->start_date?->toDateString();

        return [
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority'    => ['sometimes', 'required', Rule::enum(TaskPriority::class)],
            'status'      => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            'progress'    => ['sometimes', 'nullable', 'integer', 'between:0,100'],

            'start_date' => array_filter([
                'sometimes', 'required', 'date',
                $dueDate ? "before_or_equal:{$dueDate}" : null,
            ]),
            'due_date' => array_filter([
                'sometimes', 'nullable', 'date',
                $startDate ? "after_or_equal:{$startDate}" : null,
            ]),

            'team_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('teams', 'id')->where('project_id', $task->project_id),
            ],

            'assignees'   => ['sometimes', 'nullable', 'array'],
            'assignees.*' => ['integer', 'distinct', $assigneeExists],

            'reviewer_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('project_user', 'user_id')->where('project_id', $task->project_id),
            ],

            // new files
            'attachments'   => ['sometimes', 'nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg'],

            // ids of existing attachments to delete (must belong to THIS task)
            'remove_attachments'   => ['sometimes', 'array'],
            'remove_attachments.*' => [
                'integer', 'distinct',
                Rule::exists('attachments', 'id')
                    ->where('attachable_type', $task->getMorphClass())
                    ->where('attachable_id', $task->id),
            ],
        ];
    }
}
