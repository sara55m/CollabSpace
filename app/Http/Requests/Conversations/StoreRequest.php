<?php

namespace App\Http\Requests\Conversations;

use App\Enums\ConversationType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Project;

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
        $me = $this->user()->id;

        $isDirect = fn () => $this->input('type') === ConversationType::Direct->value;
        $isGroup  = fn () => $this->input('type') === ConversationType::Project->value;

        //skip validations of the other conversation type
        $direct = 'exclude_unless:type,' . ConversationType::Direct->value;
        $group  = 'exclude_unless:type,' . ConversationType::Project->value;

        return [
            'type' => ['required', Rule::enum(ConversationType::class)],

            // ---- direct only ----
            // the other user must share at least one project with the creator
            'user_id' => [
                $direct,
                Rule::requiredIf($isDirect),
                'bail',
                'integer',
                Rule::notIn([$me]),
                Rule::exists('project_user', 'user_id')->where(
                    fn ($q) => $q->whereIn(
                        'project_id',
                        fn ($sub) => $sub->select('project_id')
                            ->from('project_user')
                            ->where('user_id', $me)
                    )
                ),
            ],

            // ---- project group only ----
            'title' => [$group, Rule::requiredIf($isGroup), 'string', 'max:255',
                Rule::unique('conversations', 'title')
                ->where('project_id', $this->input('project_id'))],
            'avatar' => [$group, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // the creator must be a member of the project
            'project_id' => [
                $group,
                Rule::requiredIf($isGroup),
                'bail',
                'integer',
                Rule::exists('project_user', 'project_id')->where('user_id', $me),
            ],

            'participant_ids' => [$group, Rule::requiredIf($isGroup), 'array', 'min:1', 'max:49'],

            // every participant must be a member of that same project
            'participant_ids.*' => [
                $group,
                'bail',
                'integer',
                'distinct',
                Rule::notIn([$me]),
                Rule::exists('project_user', 'user_id')
                    ->where('project_id', (int) $this->input('project_id')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.exists'              => __('messages.no_shared_project'),
            'participant_ids.*.exists'    => __('messages.users_not_in_project'),
        ];
    }

}
