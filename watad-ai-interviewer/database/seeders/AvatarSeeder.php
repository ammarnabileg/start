<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Avatar;
use Illuminate\Database\Seeder;

class AvatarSeeder extends Seeder
{
    public function run(): void
    {
        $cast = [
            ['name' => 'Sara',  'role_label' => 'HR Recruiter',            'gender' => 'female', 'questioning_style' => 'friendly', 'language' => 'en', 'personality' => 'warm, encouraging, and curious; puts candidates at ease while staying focused.'],
            ['name' => 'Khaled', 'role_label' => 'HR Recruiter',           'gender' => 'male',   'questioning_style' => 'formal',   'language' => 'en', 'personality' => 'professional, structured, and precise; values clarity and concrete examples.'],
            ['name' => 'Nour',  'role_label' => 'Technical Interviewer',   'gender' => 'female', 'questioning_style' => 'probing',  'language' => 'en', 'personality' => 'sharp and detail-oriented; drills into technical depth and trade-offs.'],
            ['name' => 'Omar',  'role_label' => 'Engineering Manager',     'gender' => 'male',   'questioning_style' => 'socratic', 'language' => 'en', 'personality' => 'thoughtful mentor; explores reasoning, ownership, and leadership through questions.'],
            ['name' => 'Layla', 'role_label' => 'Sales Director',          'gender' => 'female', 'questioning_style' => 'rapid',    'language' => 'en', 'personality' => 'energetic and persuasive; tests communication, drive, and resilience.'],
            ['name' => 'Hana',  'role_label' => 'Customer Success Manager', 'gender' => 'female', 'questioning_style' => 'friendly', 'language' => 'en', 'personality' => 'empathetic and relationship-focused; probes collaboration and customer empathy.'],
        ];

        foreach ($cast as $avatar) {
            Avatar::updateOrCreate(
                ['name' => $avatar['name'], 'role_label' => $avatar['role_label']],
                [...$avatar, 'voice_provider' => 'web_speech', 'video_provider' => 'none', 'is_active' => true],
            );
        }
    }
}
