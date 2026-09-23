<?php

namespace App\Services\Commerce;

use App\Models\BusinessType;
use App\Models\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Provisions order_statuses rows from a BusinessType's default_order_statuses
 * JSON template: system-default rows (company_id = null) per business type,
 * cloned into company-owned rows the first time a company is provisioned for
 * that business type so each company can edit labels/transitions freely
 * without touching the shared template.
 */
class OrderStatusProvisioningService
{
    /**
     * Ensure system-default OrderStatus rows exist for a business type,
     * derived from its default_order_statuses JSON template. Statuses are
     * chained sequentially (each allows the next), with every non-terminal
     * status additionally allowed to transition into any terminal status.
     */
    public function ensureSystemDefaults(BusinessType $businessType): void
    {
        if (OrderStatus::query()
            ->whereNull('company_id')
            ->where('business_type_id', $businessType->id)
            ->exists()) {
            return;
        }

        $template = $businessType->default_order_statuses ?? [];

        if ($template === []) {
            return;
        }

        DB::transaction(function () use ($businessType, $template) {
            $rows = [];

            foreach (array_values($template) as $index => $entry) {
                $rows[] = OrderStatus::query()->create([
                    'company_id' => null,
                    'business_type_id' => $businessType->id,
                    'slug' => $entry['slug'],
                    'label' => $entry['label'],
                    'sort_order' => $index,
                    'is_terminal' => (bool) ($entry['is_terminal'] ?? false),
                    'is_cancellable_from' => ! ($entry['is_terminal'] ?? false),
                    'notify_customer' => (bool) ($entry['notify_customer'] ?? false),
                    'notification_template_id' => null,
                    'allowed_next_status_ids' => [],
                ]);
            }

            $terminalIds = collect($rows)->where('is_terminal', true)->pluck('id')->all();

            foreach ($rows as $index => $row) {
                if ($row->is_terminal) {
                    continue;
                }

                $next = $rows[$index + 1] ?? null;
                $allowed = $next ? [$next->id] : [];
                $allowed = array_values(array_unique(array_merge($allowed, $terminalIds)));

                $row->update(['allowed_next_status_ids' => $allowed]);
            }
        });
    }

    /**
     * Clone a company's business-type system-default statuses into
     * company-owned rows. Idempotent: no-ops if the company already has any
     * order_statuses rows.
     */
    public function provisionForCompany(int $companyId, BusinessType $businessType): void
    {
        if (OrderStatus::query()->where('company_id', $companyId)->exists()) {
            return;
        }

        $this->ensureSystemDefaults($businessType);

        $defaults = OrderStatus::query()
            ->whereNull('company_id')
            ->where('business_type_id', $businessType->id)
            ->orderBy('sort_order')
            ->get();

        if ($defaults->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($companyId, $defaults) {
            $idMap = [];
            $clones = [];

            foreach ($defaults as $default) {
                $clone = OrderStatus::query()->create([
                    'company_id' => $companyId,
                    'business_type_id' => $default->business_type_id,
                    'slug' => $default->slug,
                    'label' => $default->label,
                    'sort_order' => $default->sort_order,
                    'is_terminal' => $default->is_terminal,
                    'is_cancellable_from' => $default->is_cancellable_from,
                    'notify_customer' => $default->notify_customer,
                    'notification_template_id' => null,
                    'allowed_next_status_ids' => [],
                ]);

                $idMap[$default->id] = $clone;
                $clones[] = $clone;
            }

            foreach ($defaults as $default) {
                $clone = $idMap[$default->id];

                $allowed = collect($default->allowed_next_status_ids ?? [])
                    ->map(fn ($defaultId) => $idMap[$defaultId]->id ?? null)
                    ->filter()
                    ->values()
                    ->all();

                $clone->update(['allowed_next_status_ids' => $allowed]);
            }
        });
    }
}
