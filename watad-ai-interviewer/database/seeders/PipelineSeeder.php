<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HiringPipeline;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        $pipeline = HiringPipeline::updateOrCreate(
            ['name' => 'Default Hiring Pipeline'],
            ['is_default' => true],
        );

        $stages = [
            ['Applied', false],
            ['AI Screening', false],
            ['Shortlisted', false],
            ['Human Interview', false],
            ['Offer', false],
            ['Hired', true],
            ['Rejected', true],
        ];

        foreach ($stages as $i => [$name, $terminal]) {
            $pipeline->stages()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $i + 1, 'is_terminal' => $terminal],
            );
        }
    }
}
