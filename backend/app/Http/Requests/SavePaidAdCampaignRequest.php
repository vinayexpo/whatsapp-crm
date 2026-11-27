<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaidAdCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id && $this->user()->can('ads.manage');
    }

    public function rules(): array
    {
        return [
            'accountId' => ['required', 'uuid'], 'name' => ['required', 'string', 'max:150'],
            'destination' => ['required', Rule::in(['website', 'whatsapp'])],
            'dailyBudget' => ['required', 'integer', 'min:100', 'max:100000000'],
            'countries' => ['required', 'array', 'min:1', 'max:10'], 'countries.*' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/', 'distinct'],
            'ageMin' => ['required', 'integer', 'min:18', 'max:65'], 'ageMax' => ['required', 'integer', 'gte:ageMin', 'max:65'],
            'placements' => ['required', 'array', 'min:1', 'max:2'], 'placements.*' => ['required', Rule::in(['facebook', 'instagram']), 'distinct'],
            'startAt' => ['nullable', 'date', 'after:now'], 'endAt' => ['required', 'date', 'after:now'],
            'headline' => ['required', 'string', 'max:100'], 'message' => ['required', 'string', 'max:2000'],
            'imageUrl' => ['required', 'url:https', 'max:2000'], 'websiteUrl' => ['nullable', 'required_if:destination,website', 'url:https', 'max:2000'],
            'cta' => ['required', Rule::in(['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'BOOK_NOW', 'CONTACT_US'])],
            'specialCategories' => ['present', 'array', 'max:3'], 'specialCategories.*' => [Rule::in(['HOUSING', 'EMPLOYMENT', 'CREDIT']), 'distinct'],
        ];
    }
}
