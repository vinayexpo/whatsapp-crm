<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() && $this->user()->company_id === null;
    }

    public function rules(): array
    {
        $editing = $this->route('platformUser');
        $companyId = $this->filled('companyId') ? Company::query()->where('uuid', $this->input('companyId'))->value('id') : null;
        $superadmin = $this->input('role') === 'superadmin';

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editing instanceof User ? $editing->id : null)],
            'password' => [$editing ? 'prohibited' : 'required', 'string', 'min:8', 'max:255'],
            'role' => ['required', Rule::in(['superadmin', 'admin', 'manager', 'agent', 'branch_manager', 'staff'])],
            'status' => ['required', Rule::in(['active', 'invited', 'suspended'])],
            'companyId' => [$superadmin ? 'prohibited' : 'required', 'nullable', 'uuid', Rule::exists('companies', 'uuid')],
            'staffBranchId' => [$superadmin ? 'prohibited' : (in_array($this->input('role'), ['branch_manager', 'staff'], true) ? 'required' : 'nullable'), 'nullable', 'uuid', Rule::exists('branches', 'uuid')->where('company_id', $companyId ?? 0)],
        ];
    }
}
