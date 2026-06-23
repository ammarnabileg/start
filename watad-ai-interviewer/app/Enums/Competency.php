<?php

declare(strict_types=1);

namespace App\Enums;

enum Competency: string
{
    case Technical        = 'technical';
    case Communication    = 'communication';
    case ProblemSolving   = 'problem_solving';
    case CriticalThinking = 'critical_thinking';
    case Confidence       = 'confidence';
    case Leadership       = 'leadership';
    case CultureFit       = 'culture_fit';
    case Professionalism  = 'professionalism';
    case AiKnowledge      = 'ai_knowledge';
    case EnglishFluency   = 'english_fluency';
    case LearningAbility  = 'learning_ability';

    public function label(): string
    {
        return match ($this) {
            self::Technical        => 'Technical Skills',
            self::Communication    => 'Communication',
            self::ProblemSolving   => 'Problem Solving',
            self::CriticalThinking => 'Critical Thinking',
            self::Confidence       => 'Confidence',
            self::Leadership       => 'Leadership Potential',
            self::CultureFit       => 'Culture Fit',
            self::Professionalism  => 'Professionalism',
            self::AiKnowledge      => 'AI Knowledge',
            self::EnglishFluency   => 'English Fluency',
            self::LearningAbility  => 'Learning Ability',
        };
    }

    /** Default weight from config. */
    public function defaultWeight(): float
    {
        return (float) (config('watad.competencies')[$this->value] ?? 10);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
