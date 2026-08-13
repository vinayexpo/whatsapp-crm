<?php

namespace Database\Seeders;

use App\Models\ApiConnection;
use Illuminate\Database\Seeder;

class PipelineStagesForAllCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        // pipeline_stages.id is a single global primary key (not composite
        // with company_id), so these rows can only ever be owned by one
        // company at a time — matching the same limitation in
        // ProcessInboundWhatsAppMessage, which resolves the company from the
        // single whatsapp ApiConnection rather than per-contact. Seed for
        // that same company so inbound-message contact creation and the
        // Pipeline UI agree on which company owns the rows.
        $companyId = ApiConnection::withoutGlobalScopes()
            ->where('channel', 'whatsapp')
            ->value('company_id');

        (new PipelineStagesSeeder($companyId))->run();
    }
}
