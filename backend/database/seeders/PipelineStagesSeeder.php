<?php

namespace Database\Seeders;

use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineStagesSeeder extends Seeder
{
    private const STAGES = [
        ['id' => 'new-lead', 'name' => 'New Lead', 'color' => '#3B82C4'],
        ['id' => 'contacted', 'name' => 'Contacted', 'color' => '#7C4DFF'],
        ['id' => 'qualified', 'name' => 'Qualified', 'color' => '#F2A93B'],
        ['id' => 'negotiation', 'name' => 'Negotiation', 'color' => '#EC6F56'],
        ['id' => 'won', 'name' => 'Won', 'color' => '#2FB673'],
        ['id' => 'lost', 'name' => 'Lost', 'color' => '#9AA7A4'],
    ];

    public function __construct(private readonly ?int $companyId = null) {}

    public function run(): void
    {
        foreach (self::STAGES as $position => $stage) {
            PipelineStage::query()->updateOrCreate(
                ['id' => $stage['id']],
                ['company_id' => $this->companyId, 'name' => $stage['name'], 'color' => $stage['color'], 'position' => $position],
            );
        }
    }
}
