<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MessageTemplate;
use Illuminate\Database\Seeder;

class MessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            ['email', 'application_received', 'We received your application', "Hi {{name}},\n\nThanks for applying to {{job}} at Watad. We'll review your profile and be in touch.\n"],
            ['email', 'ai_interview_ready', 'Your AI interview is ready', "Hi {{name}},\n\nYou can now start your AI interview for {{job}}: {{link}}\n"],
            ['email', 'interview_scheduled', 'Your interview is scheduled', "Hi {{name}},\n\nYour {{type}} interview for {{job}} is scheduled for {{when}}. Join: {{link}}\n"],
            ['email', 'offer_made', 'You have an offer from Watad', "Hi {{name}},\n\nWe're excited to offer you the {{job}} role. Review and sign here: {{link}}\n"],
            ['email', 'completion', 'Thanks for completing your interview', "Hi {{name}},\n\nThank you for completing your interview for {{job}}. Our team will review and follow up.\n"],
            ['whatsapp', 'interview_reminder', null, 'Reminder: your {{type}} interview for {{job}} is at {{when}}. {{link}}'],
        ];

        foreach ($templates as [$channel, $key, $subject, $body]) {
            MessageTemplate::updateOrCreate(
                ['channel' => $channel, 'key' => $key, 'locale' => 'en'],
                ['subject' => $subject, 'body' => $body, 'is_active' => true],
            );
        }
    }
}
