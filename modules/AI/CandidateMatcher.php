<?php
declare(strict_types=1);

class CandidateMatcher
{
    /**
     * Return the overall match score for a single application.
     *
     * Combines ai_recommendations.final_score (60%) and ai_cv_analyses.match_score (40%).
     * Falls back to whichever score is available, or 0.0 if neither exists.
     *
     * @param int $applicationId
     * @return float  Score in [0, 100].
     */
    public static function getMatchScore(int $applicationId): float
    {
        $db = Database::getInstance();

        $recRow = $db->fetch(
            "SELECT r.final_score
             FROM ai_recommendations r
             JOIN ai_interviews i ON i.id = r.interview_id
             WHERE r.application_id = ?
             ORDER BY r.generated_at DESC LIMIT 1",
            [$applicationId]
        );

        $cvRow = $db->fetch(
            "SELECT match_score FROM ai_cv_analyses WHERE application_id = ? LIMIT 1",
            [$applicationId]
        );

        $recScore = $recRow ? (float)$recRow['final_score'] : null;
        $cvScore  = $cvRow  ? (float)$cvRow['match_score']  : null;

        if ($recScore !== null && $cvScore !== null) {
            return round($recScore * 0.6 + $cvScore * 0.4, 2);
        }

        if ($recScore !== null) {
            return round($recScore, 2);
        }

        if ($cvScore !== null) {
            return round($cvScore, 2);
        }

        return 0.0;
    }

    /**
     * Ask OpenAI to compare a set of candidates and answer a question about them.
     *
     * @param int[]  $applicationIds  IDs of the applications to compare.
     * @param string $question        The HR question to answer.
     * @param int    $tenantId        Tenant context for API key lookup.
     * @return string                 AI-generated answer.
     */
    public static function compareForAI(array $applicationIds, string $question, int $tenantId): string
    {
        if (empty($applicationIds)) {
            return 'No candidates provided for comparison.';
        }

        $db = Database::getInstance();

        // Build candidate profiles
        $profiles = [];
        foreach ($applicationIds as $appId) {
            $appId = (int)$appId;

            $app = $db->fetch(
                "SELECT a.id, a.status, j.title AS job_title,
                        u.first_name, u.last_name, u.email
                 FROM applications a
                 JOIN jobs j ON j.id = a.job_id
                 LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.id = ?",
                [$appId]
            );

            if (!$app) {
                continue;
            }

            $cvRow = $db->fetch(
                "SELECT match_score, skills_extracted, companies_extracted,
                        years_experience, education_level, strengths, weaknesses, notes
                 FROM ai_cv_analyses WHERE application_id = ? LIMIT 1",
                [$appId]
            );

            $recRow = $db->fetch(
                "SELECT r.final_score, r.recommendation, r.executive_summary, r.strengths, r.weaknesses
                 FROM ai_recommendations r
                 JOIN ai_interviews i ON i.id = r.interview_id
                 WHERE r.application_id = ?
                 ORDER BY r.generated_at DESC LIMIT 1",
                [$appId]
            );

            $skillRow = $db->fetch(
                "SELECT s.overall_score, s.technical_competency, s.communication,
                        s.problem_solving, s.critical_thinking, s.culture_fit
                 FROM ai_skill_scores s
                 JOIN ai_interviews i ON i.id = s.interview_id
                 WHERE s.application_id = ?
                 ORDER BY s.scored_at DESC LIMIT 1",
                [$appId]
            );

            $overallScore = self::getMatchScore($appId);
            $name         = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
            if (!$name) {
                $name = "Candidate #{$appId}";
            }

            $profile  = "--- Candidate: {$name} (Application #{$appId}) ---\n";
            $profile .= "Role Applied: " . ($app['job_title'] ?? 'N/A') . "\n";
            $profile .= "Overall Match Score: {$overallScore}/100\n";
            $profile .= "Application Status: " . ($app['status'] ?? 'N/A') . "\n";

            if ($cvRow) {
                $skills    = json_decode($cvRow['skills_extracted'] ?? '[]', true);
                $companies = json_decode($cvRow['companies_extracted'] ?? '[]', true);
                $profile  .= "CV Match Score: " . ($cvRow['match_score'] ?? 'N/A') . "/100\n";
                $profile  .= "Years Experience: " . ($cvRow['years_experience'] ?? 'N/A') . "\n";
                $profile  .= "Education: " . ($cvRow['education_level'] ?? 'N/A') . "\n";
                if ($skills) {
                    $profile .= "Skills: " . implode(', ', (array)$skills) . "\n";
                }
                if ($companies) {
                    $profile .= "Companies: " . implode(', ', (array)$companies) . "\n";
                }
                if ($cvRow['notes']) {
                    $profile .= "CV Notes: " . $cvRow['notes'] . "\n";
                }
            }

            if ($skillRow) {
                $profile .= "Interview Scores – Overall: {$skillRow['overall_score']}"
                    . ", Technical: {$skillRow['technical_competency']}"
                    . ", Communication: {$skillRow['communication']}"
                    . ", Problem Solving: {$skillRow['problem_solving']}"
                    . ", Culture Fit: {$skillRow['culture_fit']}\n";
            }

            if ($recRow) {
                $profile .= "AI Recommendation: " . ($recRow['recommendation'] ?? 'N/A') . "\n";
                if ($recRow['executive_summary']) {
                    $profile .= "Summary: " . $recRow['executive_summary'] . "\n";
                }
                $strengths = json_decode($recRow['strengths'] ?? '[]', true);
                if ($strengths) {
                    $profile .= "Strengths: " . implode('; ', (array)$strengths) . "\n";
                }
                $weaknesses = json_decode($recRow['weaknesses'] ?? '[]', true);
                if ($weaknesses) {
                    $profile .= "Weaknesses: " . implode('; ', (array)$weaknesses) . "\n";
                }
            }

            $profiles[] = $profile;
        }

        if (empty($profiles)) {
            return 'No candidate data found for the provided application IDs.';
        }

        $candidateCount = count($profiles);
        $profilesText   = implode("\n\n", $profiles);

        $systemPrompt = 'You are an expert HR consultant and talent analyst. '
            . 'You have been given detailed profiles of job candidates. '
            . 'Answer the recruiter\'s question accurately, objectively, and concisely.';

        $userPrompt = "You are comparing {$candidateCount} candidate(s) for the same role.\n\n"
            . "CANDIDATE PROFILES:\n\n{$profilesText}\n\n"
            . "RECRUITER QUESTION: {$question}\n\n"
            . "Please answer the question based on the candidate data above.";

        $response = ApiKeyManager::callOpenAI(
            [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userPrompt],
            ],
            $tenantId,
            [
                'max_tokens'  => 1000,
                'temperature' => 0.5,
                'feature'     => 'candidate_comparison',
            ]
        );

        if (!$response) {
            return 'Unable to generate comparison. Please check your OpenAI API key and try again.';
        }

        $content = $response['choices'][0]['message']['content'] ?? '';

        return trim($content) ?: 'No response generated.';
    }
}
