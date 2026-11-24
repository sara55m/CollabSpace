<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //only the project owner can create teams
        return $this->user()->can('createTeam',$this->route('project'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project=$this->route('project');

        return [
            'name'=>[
                'required','string','max:255',
                Rule::unique('teams','name')->where('project_id',$project->id)
            ],
            'members'=>['required','array','min:1'],
            //the team member must be a project member
            'members.*.id'=>['integer','distinct',Rule::exists('project_user','user_id')->where('project_id',$project->id)],

            'members.*.role'=>['nullable','string',Rule::enum(TeamRole::class)],

            'members.*.position' => ['nullable', 'string', 'max:255'],

        ];
    }
}
