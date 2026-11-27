<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetCommerce extends Command
{
    protected $signature = 'commerce:reset {--all-companies} {--confirm=} {--dry-run}';

    protected $description = 'Permanently reset local commerce data for every company, preserving CRM and Meta accounts';

    public function handle(): int
    {
        if (! $this->option('all-companies')) {
            $this->error('Explicit --all-companies is required.');

            return self::FAILURE;
        }
        $tables = ['order_sessions', 'payments', 'order_items', 'orders', 'inventory', 'branch_prices', 'branch_products', 'addon_definition_product', 'product_variants', 'products', 'categories', 'addon_definitions', 'delivery_zones', 'branches', 'commerce_settings'];
        $counts = [];
        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = DB::table($table)->count();
            }
        }
        $this->table(['Table', 'Rows'], collect($counts)->map(fn ($n, $t) => [$t, $n])->values()->all());
        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }
        if ($this->option('confirm') !== 'DELETE-ALL-COMMERCE') {
            $this->error('Pass --confirm=DELETE-ALL-COMMERCE to execute the irreversible reset. Stop commerce queue workers before running.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($counts) {
            DB::table('users')->whereNotNull('staff_branch_id')->update(['staff_branch_id' => null]);
            foreach (array_keys($counts) as $table) {
                DB::table($table)->delete();
            }
            DB::table('order_statuses')->whereNotNull('company_id')->delete();
        });
        $this->info('Local commerce reset completed. Contacts, conversations, companies, API connections and remote Meta catalogs were preserved.');

        return self::SUCCESS;
    }
}
