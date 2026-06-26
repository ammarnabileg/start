<?php
declare(strict_types=1);

class OfferController
{
    public static function index(Request $r): void
    {
        Auth::requirePermission('offers.view');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $offers = $db->fetchAll(
            "SELECT o.*, u.first_name, u.last_name, u.email, j.title AS job_title
             FROM offers o
             JOIN applications a ON a.id = o.application_id
             JOIN users u ON u.id = a.user_id
             JOIN jobs j ON j.id = a.job_id
             WHERE o.tenant_id = ?
             ORDER BY o.created_at DESC",
            [$tenantId]
        );

        renderView('hr/offers', compact('offers'), 'app');
    }

    public static function create(Request $r): void
    {
        Auth::requirePermission('offers.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $data = $r->only(['application_id', 'title', 'salary', 'currency', 'start_date', 'expiry_date', 'notes', 'benefits']);
        $v = Validator::make($data, [
            'application_id' => 'required|integer',
            'title'          => 'required|max:255',
            'salary'         => 'required|numeric',
            'currency'       => 'required|max:10',
            'expiry_date'    => 'required|date',
        ]);
        if ($v->fails()) { Response::error($v->firstError(), 422, $v->errors()); return; }

        $app = $db->fetch("SELECT id FROM applications WHERE id = ? AND tenant_id = ?", [(int)$data['application_id'], $tenantId]);
        if (!$app) { Response::error('Application not found.', 404); return; }

        $now = date('Y-m-d H:i:s');
        $id  = $db->insert('offers', [
            'tenant_id'      => $tenantId,
            'application_id' => (int)$data['application_id'],
            'title'          => $data['title'],
            'salary'         => (float)$data['salary'],
            'currency'       => strtoupper($data['currency']),
            'start_date'     => $data['start_date'] ?? null,
            'expiry_date'    => $data['expiry_date'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'draft',
            'created_by'     => Auth::id(),
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);

        // Add benefits
        $benefits = (array)($data['benefits'] ?? []);
        foreach ($benefits as $benefit) {
            if (trim($benefit)) {
                $db->insert('offer_benefits', [
                    'offer_id'   => $id,
                    'benefit'    => trim($benefit),
                    'created_at' => $now,
                ]);
            }
        }

        Response::success(['id' => $id], 'Offer created.');
    }

    public static function send(Request $r, int $id): void
    {
        Auth::requirePermission('offers.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $offer = $db->fetch(
            "SELECT o.*, u.email, u.first_name, j.title AS job_title
             FROM offers o JOIN applications a ON a.id = o.application_id
             JOIN users u ON u.id = a.user_id JOIN jobs j ON j.id = a.job_id
             WHERE o.id = ? AND o.tenant_id = ?",
            [$id, $tenantId]
        );

        if (!$offer) { Response::error('Not found', 404); return; }
        if ($offer['status'] !== 'draft') { Response::error('Offer already sent.', 422); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('offers', ['status' => 'sent', 'sent_at' => $now, 'updated_at' => $now], ['id' => $id]);
        $db->update('applications', ['status' => 'offer_extended', 'updated_at' => $now], ['id' => (int)$offer['application_id']]);

        $subject = 'Job Offer — ' . $offer['job_title'];
        $body = "Dear {$offer['first_name']},\n\nWe are pleased to extend an offer for the position of {$offer['title']}.\n\nSalary: {$offer['salary']} {$offer['currency']}\nExpiry: " . date('M j, Y', strtotime($offer['expiry_date'])) . "\n\nPlease log in to your candidate portal to review and respond to this offer.\n";
        @mail($offer['email'], $subject, $body, 'From: ' . Env::get('MAIL_FROM', 'noreply@example.com'));

        Response::success(null, 'Offer sent.');
    }

    public static function revoke(Request $r, int $id): void
    {
        Auth::requirePermission('offers.manage');
        $db       = Database::getInstance();
        $tenantId = (int)Auth::tenantId();

        $offer = $db->fetch("SELECT * FROM offers WHERE id = ? AND tenant_id = ?", [$id, $tenantId]);
        if (!$offer) { Response::error('Not found', 404); return; }

        $now = date('Y-m-d H:i:s');
        $db->update('offers', ['status' => 'revoked', 'updated_at' => $now], ['id' => $id]);

        Response::success(null, 'Offer revoked.');
    }
}
