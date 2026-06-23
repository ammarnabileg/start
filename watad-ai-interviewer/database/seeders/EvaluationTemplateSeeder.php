<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EvaluationTemplate;
use Illuminate\Database\Seeder;

class EvaluationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $template = EvaluationTemplate::updateOrCreate(
            ['name' => 'General Interview Evaluation'],
            ['is_default' => true, 'is_active' => true],
        );

        $criteria = [
            ['Technical depth', 'rating', 3],
            ['Problem solving', 'rating', 3],
            ['Communication', 'rating', 2],
            ['Culture fit', 'rating', 1],
            ['Takes ownership?', 'boolean', 1],
            ['Seniority fit', 'select', 1, ['Junior', 'Mid', 'Senior', 'Lead']],
        ];

        foreach ($criteria as $i => $c) {
            $template->criteria()->updateOrCreate(
                ['label' => $c[0]],
                ['type' => $c[1], 'weight' => $c[2], 'options' => $c[3] ?? null, 'position' => $i, 'is_required' => true],
            );
        }
    }
}
