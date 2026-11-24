<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TeamRole;

class UpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update',$this->route('team'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project=$this->route('project');
        $team=$this->route('team');

        return [
            'name'=>[
                'sometimes','required','string','max:255',
                Rule::unique('teams','name')->where('project_id',$project->id)->ignore($team->id)
            ],
            'members'=>['sometimes','required','array','min:1'],
            //the team member must be a project member
            'members.*.id'=>['integer','distinct',Rule::exists('project_user','user_id')->where('project_id',$project->id)],

            'members.*.role'=>['nullable','string',Rule::enum(TeamRole::class)],

            'members.*.position' => ['nullable', 'string', 'max:255'],

        ];
    }
}
