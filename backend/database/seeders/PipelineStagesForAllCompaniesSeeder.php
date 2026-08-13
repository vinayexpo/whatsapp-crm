<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class PipelineStagesForAllCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        $companyIds = Company::query()->pluck('id');

        if ($companyIds->isEmpty()) {
            (new PipelineStagesSeeder)->run();

            return;
        }

        foreach ($companyIds as $companyId) {
            (new PipelineStagesSeeder($companyId))->run();
        }
    }
}
