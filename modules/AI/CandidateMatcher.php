<?php

namespace App\Modules\AI;

use App\Core\Database;

/**
 * Matches and compares candidates against jobs using the CV analyzer, and
 * assembles side-by-side comparison data from stored evaluations. All data
 * access uses real SQL through the shared Database wrapper.
 */
class CandidateMatcher
{
    private Database $db;
    private OpenAIService $ai;
    private CVAnalyzer $analyzer;

    public function __construct(?Database $db = null, ?OpenAIService $ai = null, ?CVAnalyzer $analyzer = null)
    {
        $this->db = $db ?? Database::instance();
        $this->ai = $ai ?? new OpenAIService();
        $this->analyzer = $analyzer ?? new CVAnalyzer($this->ai);
    }

    /**
     * Score a specific set of candidates against a job, sorted best-first.
     *
     * @param array<int,int> $candidateIds
     * @return array<int,array{candidate_id:int,name:string,score:int,recommendation:string,summary:string}>
     */
    public function matchCandidatesToJob(int $jobId, array $candidateIds): array
    {
        $job = $this->loadJob($jobId);
        if ($job === null) {
            return [];
        }

        $jobText  = $this->jobText($job);
        $criteria = $this->jobCriteria($job);

        $results = [];
        foreach (array_values(array_unique(array_map('intval', $candidateIds))) as $cid) {
            if ($cid <= 0) {
                continue;
            }
            $candidate = $this->loadCandidate($cid);
            if ($candidate === null) {
                continue;
            }

            $cvText = trim((string) ($candidate['cv_text'] ?? ''));
            $analysis = $this->analyzer->analyze($cvText, $jobText, $criteria);

            $results[] = [
                'candidate_id'   => $cid,
                'name'           => $this->candidateName($candidate),
                'score'          => (int) ($analysis['overall_match_score'] ?? 0),
                'recommendation' => (string) ($analysis['recommendation'] ?? 'maybe'),
                'summary'        => (string) ($analysis['summary'] ?? ''),
            ];
        }

        usort($results, static fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }

    /**
     * Find and rank candidates for a job, optionally restricted to a talent pool.
     * Without a pool, all candidates of the job's tenant are considered.
     *
     * @return array<int,array{candidate_id:int,name:string,score:int,recommendation:string,summary:string}>
     */
    public function findMatchingCandidates(int $jobId, ?int $talentPoolId = null): array
    {
        $job = $this->loadJob($jobId);
        if ($job === null) {
            return [];
        }
        $tenantId = (int) ($job['tenant_id'] ?? 0);

        if ($talentPoolId !== null) {
            $rows = $this->db->fetchAll(
                'SELECT tpc.candidate_id
                 FROM talent_pool_candidates tpc
                 INNER JOIN candidates c ON c.id = tpc.candidate_id
                 WHERE tpc.pool_id = :pool AND c.tenant_id = :tenant',
                [':pool' => $talentPoolId, ':tenant' => $tenantId]
            );
        } else {
            $rows = $this->db->fetchAll(
                'SELECT id AS candidate_id FROM candidates WHERE tenant_id = :tenant',
                [':tenant' => $tenantId]
            );
        }

        $ids = array_map(static fn($r) => (int) $r['candidate_id'], $rows);
        if ($ids === []) {
            return [];
        }

        return $this->matchCandidatesToJob($jobId, $ids);
    }

    /**
     * Build a side-by-side comparison of candidates using their most recent
     * interview evaluation (skills + DISC personality), where available.
     *
     * @param array<int,int> $candidateIds
     * @return array{candidates:array<int,array<string,mixed>>,skills:array<int,string>}
     */
    public function compareProfiles(array $candidateIds): array
    {
        $candidates = [];
        $skillUnion = [];

        foreach (array_values(array_unique(array_map('intval', $candidateIds))) as $cid) {
            if ($cid <= 0) {
                continue;
            }
            $candidate = $this->loadCandidate($cid);
            if ($candidate === null) {
                continue;
            }

            $eval = $this->latestEvaluation($cid);

            $skills = [];
            $disc = ['D' => null, 'I' => null, 'S' => null, 'C' => null];
            $overall = null;
            $recommendation = null;

            if ($eval !== null) {
                $overall = $eval['overall_score'] !== null ? (float) $eval['overall_score'] : null;
                $recommendation = $eval['recommendation'] ?? null;
                $evalId = (int) $eval['id'];

                foreach ($this->db->fetchAll(
                    'SELECT skill_name, score FROM skill_scores WHERE evaluation_id = :eid',
                    [':eid' => $evalId]
                ) as $s) {
                    $name = (string) $s['skill_name'];
                    $skills[$name] = (float) $s['score'];
                    $skillUnion[$name] = true;
                }

                $pers = $this->db->fetch(
                    'SELECT disc_d, disc_i, disc_s, disc_c FROM personality_analysis WHERE evaluation_id = :eid LIMIT 1',
                    [':eid' => $evalId]
                );
                if ($pers !== null) {
                    $disc = [
                        'D' => $pers['disc_d'] !== null ? (float) $pers['disc_d'] : null,
                        'I' => $pers['disc_i'] !== null ? (float) $pers['disc_i'] : null,
                        'S' => $pers['disc_s'] !== null ? (float) $pers['disc_s'] : null,
                        'C' => $pers['disc_c'] !== null ? (float) $pers['disc_c'] : null,
                    ];
                }
            }

            $candidates[] = [
                'id'             => $cid,
                'name'           => $this->candidateName($candidate),
                'overall_score'  => $overall,
                'recommendation' => $recommendation,
                'skills'         => $skills,
                'disc'           => $disc,
            ];
        }

        $skillNames = array_keys($skillUnion);
        sort($skillNames);

        // Ensure every candidate exposes the full union for clean table rendering.
        foreach ($candidates as &$cand) {
            foreach ($skillNames as $skill) {
                if (!array_key_exists($skill, $cand['skills'])) {
                    $cand['skills'][$skill] = null;
                }
            }
        }
        unset($cand);

        return [
            'candidates' => $candidates,
            'skills'     => $skillNames,
        ];
    }

    // ----------------------------------------------------------------------
    // Data access
    // ----------------------------------------------------------------------

    /**
     * @return array<string,mixed>|null
     */
    private function loadJob(int $jobId): ?array
    {
        return $this->db->fetch(
            'SELECT id, tenant_id, title, description, requirements, ai_criteria FROM jobs WHERE id = :id LIMIT 1',
            [':id' => $jobId]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadCandidate(int $candidateId): ?array
    {
        return $this->db->fetch(
            'SELECT id, tenant_id, first_name, last_name, email, cv_text FROM candidates WHERE id = :id LIMIT 1',
            [':id' => $candidateId]
        );
    }

    /**
     * Most recent completed (or latest) interview evaluation for a candidate,
     * reached through applications -> interviews -> interview_evaluations.
     *
     * @return array<string,mixed>|null
     */
    private function latestEvaluation(int $candidateId): ?array
    {
        return $this->db->fetch(
            'SELECT e.id, e.overall_score, e.recommendation, e.summary
             FROM interview_evaluations e
             INNER JOIN interviews i ON i.id = e.interview_id
             INNER JOIN applications a ON a.id = i.application_id
             WHERE a.candidate_id = :cid
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 1',
            [':cid' => $candidateId]
        );
    }

    // ----------------------------------------------------------------------
    // Shaping helpers
    // ----------------------------------------------------------------------

    /**
     * @param array<string,mixed> $job
     */
    private function jobText(array $job): string
    {
        $parts = [];
        if (trim((string) ($job['title'] ?? '')) !== '') {
            $parts[] = 'Title: ' . trim((string) $job['title']);
        }
        if (trim((string) ($job['description'] ?? '')) !== '') {
            $parts[] = trim((string) $job['description']);
        }
        if (trim((string) ($job['requirements'] ?? '')) !== '') {
            $parts[] = 'Requirements:' . "\n" . trim((string) $job['requirements']);
        }
        return implode("\n\n", $parts);
    }

    /**
     * Decode the job's ai_criteria JSON into the criteria contract.
     *
     * @param array<string,mixed> $job
     * @return array<int,array{criterion_name:string,weight:float,description:string}>
     */
    private function jobCriteria(array $job): array
    {
        $raw = $job['ai_criteria'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $c) {
            if (!is_array($c)) {
                if (is_string($c) && trim($c) !== '') {
                    $out[] = ['criterion_name' => trim($c), 'weight' => 1.0, 'description' => ''];
                }
                continue;
            }
            $name = trim((string) ($c['criterion_name'] ?? $c['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'criterion_name' => $name,
                'weight'         => (float) ($c['weight'] ?? 1),
                'description'    => trim((string) ($c['description'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $candidate
     */
    private function candidateName(array $candidate): string
    {
        $name = trim(((string) ($candidate['first_name'] ?? '')) . ' ' . ((string) ($candidate['last_name'] ?? '')));
        if ($name !== '') {
            return $name;
        }
        $email = trim((string) ($candidate['email'] ?? ''));
        return $email !== '' ? $email : ('Candidate #' . (int) ($candidate['id'] ?? 0));
    }
}
