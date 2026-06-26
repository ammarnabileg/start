<?php
declare(strict_types=1);

class ComparisonController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('comparisons.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $comparisons = $db->fetchAll(
            "SELECT cc.*, u.first_name, u.last_name, j.title AS job_title,
                    COUNT(ci.id) AS candidate_count
             FROM candidate_comparisons cc
             JOIN users u ON u.id = cc.created_by
             LEFT JOIN jobs j ON j.id = cc.job_id
             LEFT JOIN candidate_comparison_items ci ON ci.comparison_id = cc.id
             WHERE cc.tenant_id = ?
             GROUP BY cc.id
             ORDER BY cc.created_at DESC",
            [$tenantId]
        );

        $jobs = $db->fetchAll("SELECT id, title FROM jobs WHERE tenant_id = ? ORDER BY title", [$tenantId]);

        renderView('hr/comparisons', compact('comparisons', 'jobs'), 'app');
    }

    public static function create(Request $r): void
    {
        Auth::requirePermission('comparisons.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $data = $r->only(['title', 'job_id', 'application_ids']);
        $v = Validator::make($data, [
            'title'           => 'required|max:255',
            'application_ids' => 'required',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $appIds = (array)$data['application_ids'];
        if (count($appIds) < 2) { Response::error('Please select at least 2 candidates to compare.', 422); return; }
        if (count($appIds) > 10) { Response::error('Maximum 10 candidates can be compared at once.', 422); return; }

        $now = date('Y-m-d H:i:s');
        $id  = $db->insert('candidate_comparisons', [
            'tenant_id'  => $tenantId,
            'title'      => $data['title'],
            'job_id'     => !empty($data['job_id']) ? (int)$data['job_id'] : null,
            'created_by' => Auth::id(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($appIds as $appId) {
            $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [(int)$appId, $tenantId]);
            if ($app) {
                $db->insert('candidate_comparison_items', [
                    'comparison_id'  => $id,
                    'application_id' => (int)$appId,
                    'created_at'     => $now,
                ]);
            }
        }

        Response::success(['id' => $id], 'Comparison created.');
    }

    public static function show(Request $r, int $id): void
    {
        Auth::requirePermission('comparisons.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $comparison = $db->fetch(
            "SELECT cc.*, j.title AS job_title FROM candidate_comparisons cc
             LEFT JOIN jobs j ON j.id = cc.job_id
             WHERE cc.id = ? AND cc.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$comparison) {
            http_response_code(404);
            renderView('errors/404', [], 'app');
            return;
        }

        $items = $db->fetchAll(
            "SELECT ci.*, a.status,
                    u.first_name, u.last_name, u.email,
                    ai.overall_score AS ai_score, ai.id AS ai_interview_id,
                    cp.years_experience, cp.current_job_title
             FROM candidate_comparison_items ci
             JOIN applications a ON a.id = ci.application_id
             JOIN users u ON u.id = a.user_id
             LEFT JOIN ai_interviews ai ON ai.application_id = a.id AND ai.status = 'completed'
             LEFT JOIN candidate_profiles cp ON cp.user_id = a.user_id
             WHERE ci.comparison_id = ?
             ORDER BY ai.overall_score DESC",
            [$id]
        );

        // Load skill scores for each
        foreach ($items as &$item) {
            if ($item['ai_interview_id']) {
                $item['skills'] = $db->fetchAll(
                    "SELECT * FROM ai_skill_scores WHERE interview_id = ? ORDER BY skill_name",
                    [(int)$item['ai_interview_id']]
                );
                $item['personality'] = $db->fetch(
                    "SELECT * FROM ai_personality_analysis WHERE interview_id = ?",
                    [(int)$item['ai_interview_id']]
                );
            } else {
                $item['skills'] = [];
                $item['personality'] = null;
            }
        }
        unset($item);

        renderView('hr/comparison-show', compact('comparison', 'items'), 'app');
    }
}
