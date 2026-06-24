<?php
namespace App\Modules\Candidates;

use App\Core\Database;

/**
 * Candidate business logic: CRUD, CV/job match analysis and the assembled
 * 360-degree profile used by the HR candidate view.
 */
class CandidateService
{
    private CandidateRepository $repository;
    private Database $db;

    public function __construct(?CandidateRepository $repository = null, ?Database $db = null)
    {
        $this->repository = $repository ?? new CandidateRepository();
        $this->db = $db ?? Database::instance();
    }

    public function getCandidates(int $tenantId, array $filters = []): array
    {
        return $this->repository->findAll($tenantId, $filters);
    }

    /**
     * Fetch a single candidate, optionally enforcing tenant ownership.
     */
    public function getCandidate(int $id, ?int $tenantId = null): ?array
    {
        $candidate = $this->repository->findById($id);
        if ($candidate === null) {
            return null;
        }
        if ($tenantId !== null && (int) $candidate['tenant_id'] !== $tenantId) {
            return null;
        }
        return $candidate;
    }

    public function createCandidate(array $data): array
    {
        $id = $this->repository->create($data);
        return $this->repository->findById($id) ?? array_merge(['id' => $id], $data);
    }

    public function updateCandidate(int $id, array $data): ?array
    {
        $this->repository->update($id, $data);
        return $this->repository->findById($id);
    }

    /**
     * Run AI CV-vs-job analysis for a candidate against a job and persist the
     * resulting match score onto the relevant application.
     *
     * Gracefully degrades when the optional App\Modules\AI\CVAnalyzer service is
     * not present: a deterministic keyword-overlap heuristic is used instead so
     * the platform still produces a usable score.
     *
     * @return array<string,mixed> analysis result (always contains match_score)
     */
    public function analyzeCV(int $candidateId, int $jobId): array
    {
        $candidate = $this->repository->findById($candidateId);
        if ($candidate === null) {
            return ['error' => 'Candidate not found', 'match_score' => 0];
        }
        $job = $this->db->fetch('SELECT * FROM jobs WHERE id = :id LIMIT 1', [':id' => $jobId]);
        if ($job === null) {
            return ['error' => 'Job not found', 'match_score' => 0];
        }

        $cvText = (string) ($candidate['cv_text'] ?? '');
        $jobDescription = trim((string) ($job['description'] ?? '') . "\n\n" . (string) ($job['requirements'] ?? ''));
        $criteria = $this->decodeJson($job['ai_criteria'] ?? null);

        $analysis = null;
        if (class_exists('App\\Modules\\AI\\CVAnalyzer')) {
            try {
                $analyzerClass = 'App\\Modules\\AI\\CVAnalyzer';
                $analyzer = new $analyzerClass();
                $analysis = $analyzer->analyze($cvText, $jobDescription, $criteria);
            } catch (\Throwable $e) {
                logger('CVAnalyzer failed: ' . $e->getMessage(), 'error');
                $analysis = null;
            }
        }

        if (!is_array($analysis)) {
            $analysis = $this->heuristicMatch($cvText, $jobDescription, $criteria);
        }

        // Normalise the score key the AI service may use.
        $score = $analysis['match_score']
            ?? $analysis['ai_match_score']
            ?? $analysis['score']
            ?? $analysis['overall_score']
            ?? 0;
        $score = (float) $score;
        $analysis['match_score'] = $score;

        // Persist onto the candidate's application for this job, if one exists.
        $application = $this->db->fetch(
            'SELECT * FROM applications WHERE candidate_id = :cid AND job_id = :jid ORDER BY applied_at DESC LIMIT 1',
            [':cid' => $candidateId, ':jid' => $jobId]
        );
        if ($application !== null) {
            $this->db->update('applications', ['ai_match_score' => $score], ['id' => (int) $application['id']]);
            $analysis['application_id'] = (int) $application['id'];
        }

        return $analysis;
    }

    /**
     * Assemble a full candidate profile: core record, applications, interviews
     * and the latest evaluation with its skill scores, personality analysis and
     * red flags.
     *
     * @return array<string,mixed>|null
     */
    public function getFullProfile(int $candidateId): ?array
    {
        $candidate = $this->repository->findById($candidateId);
        if ($candidate === null) {
            return null;
        }

        $applications = $this->repository->getApplications($candidateId);
        $interviews = $this->repository->getInterviews($candidateId);

        $evaluation = $this->db->fetch(
            'SELECT e.* FROM interview_evaluations e
                INNER JOIN interviews i ON i.id = e.interview_id
                INNER JOIN applications a ON a.id = i.application_id
                WHERE a.candidate_id = :cid
                ORDER BY e.created_at DESC LIMIT 1',
            [':cid' => $candidateId]
        );

        $skillScores = [];
        $personality = null;
        $redFlags = [];
        if ($evaluation !== null) {
            $evalId = (int) $evaluation['id'];
            $skillScores = $this->db->fetchAll(
                'SELECT * FROM skill_scores WHERE evaluation_id = :eid ORDER BY score DESC',
                [':eid' => $evalId]
            );
            $personality = $this->db->fetch(
                'SELECT * FROM personality_analysis WHERE evaluation_id = :eid LIMIT 1',
                [':eid' => $evalId]
            );
            $redFlags = $this->db->fetchAll(
                "SELECT * FROM red_flags WHERE evaluation_id = :eid
                    ORDER BY FIELD(severity,'high','medium','low'), id ASC",
                [':eid' => $evalId]
            );
        }

        return [
            'candidate'            => $candidate,
            'applications'         => $applications,
            'interviews'           => $interviews,
            'evaluation'           => $evaluation,
            'skill_scores'         => $skillScores,
            'personality_analysis' => $personality,
            'red_flags'            => $redFlags,
        ];
    }

    public function getRepository(): CandidateRepository
    {
        return $this->repository;
    }

    /**
     * Deterministic fallback match scoring based on keyword overlap between the
     * CV text and the job description/criteria. Returns the same shape callers
     * expect from the AI analyzer.
     *
     * @param array<int|string,mixed> $criteria
     * @return array<string,mixed>
     */
    private function heuristicMatch(string $cvText, string $jobDescription, array $criteria): array
    {
        $cv = strtolower($cvText);
        $keywords = $this->extractKeywords($jobDescription);

        // Fold any criteria names/keywords into the keyword set.
        foreach ($criteria as $key => $value) {
            $candidateTerms = [];
            if (is_string($key) && !is_numeric($key)) {
                $candidateTerms[] = $key;
            }
            if (is_string($value)) {
                $candidateTerms[] = $value;
            } elseif (is_array($value)) {
                foreach (['name', 'criterion', 'criterion_name', 'skill'] as $field) {
                    if (!empty($value[$field]) && is_string($value[$field])) {
                        $candidateTerms[] = $value[$field];
                    }
                }
            }
            foreach ($candidateTerms as $term) {
                foreach ($this->extractKeywords($term) as $kw) {
                    $keywords[$kw] = true;
                }
            }
        }

        $matched = [];
        $missing = [];
        foreach (array_keys($keywords) as $kw) {
            if ($cv !== '' && str_contains($cv, $kw)) {
                $matched[] = $kw;
            } else {
                $missing[] = $kw;
            }
        }

        $total = count($keywords);
        $score = $total > 0 ? round((count($matched) / $total) * 100, 2) : 0.0;

        return [
            'match_score'     => $score,
            'matched_keywords' => array_values(array_slice($matched, 0, 30)),
            'missing_keywords' => array_values(array_slice($missing, 0, 30)),
            'method'          => 'heuristic',
            'summary'         => sprintf(
                'Heuristic keyword match: %d of %d job keywords found in the CV.',
                count($matched),
                $total
            ),
        ];
    }

    /**
     * Extract a deduplicated set of meaningful keywords (length >= 3, stopwords
     * removed) from text. Returns an associative set for O(1) membership.
     *
     * @return array<string,bool>
     */
    private function extractKeywords(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9+#\.\s]/', ' ', $text) ?? '';
        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_flip([
            'the', 'and', 'for', 'with', 'you', 'are', 'our', 'will', 'have', 'this',
            'that', 'who', 'has', 'all', 'your', 'their', 'from', 'they', 'job', 'role',
            'work', 'team', 'must', 'should', 'able', 'using', 'use', 'including', 'etc',
            'a', 'an', 'to', 'of', 'in', 'on', 'or', 'as', 'at', 'be', 'is', 'we', 'it',
        ]);
        $keywords = [];
        foreach ($tokens as $token) {
            $token = trim($token, '.');
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (isset($stop[$token])) {
                continue;
            }
            $keywords[$token] = true;
        }
        return $keywords;
    }

    /**
     * @return array<int|string,mixed>
     */
    private function decodeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
