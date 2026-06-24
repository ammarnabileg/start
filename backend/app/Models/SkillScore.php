<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillScore extends Model
{
    protected $fillable = [
        'ai_evaluation_id', 'candidate_id', 'skill_key', 'skill_name',
        'skill_name_ar', 'score', 'weight', 'confidence', 'evidence',
    ];

    public function evaluation() { return $this->belongsTo(AiEvaluation::class, 'ai_evaluation_id'); }
    public function candidate() { return $this->belongsTo(Candidate::class); }

    const DEFAULT_SKILLS = [
        ['key' => 'technical_competency', 'name' => 'Technical Competency', 'name_ar' => 'الكفاءة التقنية', 'weight' => 18],
        ['key' => 'communication', 'name' => 'Communication Skills', 'name_ar' => 'مهارات التواصل', 'weight' => 12],
        ['key' => 'problem_solving', 'name' => 'Problem Solving', 'name_ar' => 'حل المشكلات', 'weight' => 12],
        ['key' => 'critical_thinking', 'name' => 'Critical Thinking', 'name_ar' => 'التفكير النقدي', 'weight' => 10],
        ['key' => 'self_confidence', 'name' => 'Self Confidence', 'name_ar' => 'الثقة بالنفس', 'weight' => 8],
        ['key' => 'leadership', 'name' => 'Leadership', 'name_ar' => 'القيادة', 'weight' => 8],
        ['key' => 'cultural_fit', 'name' => 'Cultural Fit', 'name_ar' => 'الانسجام مع ثقافة العمل', 'weight' => 8],
        ['key' => 'professionalism', 'name' => 'Professionalism', 'name_ar' => 'الاحترافية', 'weight' => 8],
        ['key' => 'ai_knowledge', 'name' => 'AI Knowledge', 'name_ar' => 'معرفة الذكاء الاصطناعي', 'weight' => 6],
        ['key' => 'english_proficiency', 'name' => 'English Proficiency', 'name_ar' => 'إتقان اللغة الإنجليزية', 'weight' => 6],
        ['key' => 'learning_ability', 'name' => 'Learning Ability', 'name_ar' => 'القدرة على التعلم', 'weight' => 4],
    ];
}
