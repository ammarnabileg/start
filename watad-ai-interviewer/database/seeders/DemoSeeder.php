<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Competency;
use App\Enums\RoleSlug;
use App\Models\Avatar;
use App\Models\Department;
use App\Models\HiringPipeline;
use App\Models\InterviewInvitation;
use App\Models\InterviewTemplate;
use App\Models\JobPosition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $roleIds = Role::pluck('id', 'slug');

        $users = [
            ['admin@watad.com',     'Watad Admin',  RoleSlug::SuperAdmin],
            ['hr@watad.com',        'HR Manager',   RoleSlug::HrManager],
            ['recruiter@watad.com', 'Recruiter',    RoleSlug::Recruiter],
        ];
        foreach ($users as [$email, $name, $role]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => Hash::make('password'), 'is_active' => true],
            );
            $user->roles()->syncWithoutDetaching([$roleIds[$role->value]]);
        }

        $manager = User::where('email', 'hr@watad.com')->first();
        $dept    = Department::updateOrCreate(['slug' => 'engineering'], ['name' => 'Engineering', 'manager_id' => $manager?->id]);
        $avatar  = Avatar::where('name', 'Sara')->first();

        $template = InterviewTemplate::updateOrCreate(
            ['name' => 'Standard Screening'],
            [
                'department_id'    => $dept->id,
                'avatar_id'        => $avatar?->id,
                'mode'             => 'text',
                'language'         => 'en',
                'min_questions'    => 6,
                'max_questions'    => 12,
                'max_duration_min' => 20,
                'difficulty'       => 'adaptive',
                'follow_up_depth'  => 2,
                'config'           => ['detect_contradictions' => true, 'measure_confidence' => true, 'english_eval' => true],
                'is_active'        => true,
            ],
        );

        foreach (Competency::cases() as $competency) {
            $template->competencies()->updateOrCreate(
                ['competency' => $competency->value],
                ['weight' => $competency->defaultWeight(), 'is_enabled' => true],
            );
        }

        $pipeline = HiringPipeline::where('is_default', true)->first();

        $job = JobPosition::updateOrCreate(
            ['slug' => 'senior-backend-engineer-demo'],
            [
                'department_id'    => $dept->id,
                'created_by'       => $manager?->id,
                'title'            => 'Senior Backend Engineer',
                'seniority'        => 'senior',
                'employment_type'  => 'full_time',
                'location'         => 'Cairo',
                'is_remote'        => true,
                'description'      => 'Design, build and scale Watad backend services and APIs.',
                'responsibilities' => ['Design and own backend services', 'Mentor junior engineers', 'Drive system-design decisions'],
                'requirements'     => [
                    ['skill' => 'PHP / Laravel', 'weight' => 3, 'required' => true],
                    ['skill' => 'MySQL & data modeling', 'weight' => 2, 'required' => true],
                    ['skill' => 'System design', 'weight' => 3, 'required' => true],
                    ['skill' => 'AI / LLM integration', 'weight' => 1, 'required' => false],
                ],
                'salary_min'          => 40000,
                'salary_max'          => 70000,
                'currency'            => 'EGP',
                'default_template_id' => $template->id,
                'pipeline_id'         => $pipeline?->id,
                'status'              => 'open',
                'openings'            => 2,
            ],
        );

        $token = str_pad('demo-invite', 40, '0');
        InterviewInvitation::updateOrCreate(
            ['token' => $token],
            [
                'job_position_id' => $job->id,
                'template_id'     => $template->id,
                'avatar_id'       => $avatar?->id,
                'created_by'      => $manager?->id,
                'status'          => 'pending',
                'expires_at'      => now()->addDays(30),
            ],
        );

        $this->command?->info('Demo HR login: admin@watad.com / password');
        $this->command?->info('Demo candidate interview link: '.url("/i/{$token}"));
    }
}
