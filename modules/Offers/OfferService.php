<?php
namespace App\Modules\Offers;

use App\Core\Database;

/**
 * Offer business logic. Offers carry no tenant_id; tenant scoping is achieved by
 * joining applications -> jobs. Data access is kept inline via the Database
 * helper to stay self-contained.
 */
class OfferService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * List offers for a tenant with candidate name + job title.
     *
     * Supported filters: status.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getOffers(int $tenantId, array $filters = []): array
    {
        $params = [':tenant_id' => $tenantId];
        $where = ['j.tenant_id = :tenant_id'];

        if (!empty($filters['status'])) {
            $where[] = 'o.status = :status';
            $params[':status'] = $filters['status'];
        }

        $sql = 'SELECT o.*, a.job_id, a.candidate_id, a.pipeline_stage,
                    j.title AS job_title,
                    c.first_name, c.last_name, c.email AS candidate_email,
                    CONCAT(COALESCE(c.first_name, \'\'), \' \', COALESCE(c.last_name, \'\')) AS candidate_name
                FROM offers o
                INNER JOIN applications a ON a.id = o.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY o.created_at DESC';

        return $this->db->fetchAll($sql, $params);
    }

    public function getOffer(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM offers WHERE id = :id LIMIT 1', [':id' => $id]);
    }

    public function getOfferForTenant(int $id, int $tenantId): ?array
    {
        return $this->db->fetch(
            'SELECT o.* FROM offers o
                INNER JOIN applications a ON a.id = o.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                WHERE o.id = :id AND j.tenant_id = :tid LIMIT 1',
            [':id' => $id, ':tid' => $tenantId]
        );
    }

    public function findByToken(string $token): ?array
    {
        return $this->db->fetch('SELECT * FROM offers WHERE token = :token LIMIT 1', [':token' => $token]);
    }

    /**
     * Create a draft offer with a unique token.
     *
     * @param array<string,mixed> $data salary, currency, start_date, expiry_date, notes
     * @return array<string,mixed> the created offer row
     */
    public function createOffer(int $applicationId, array $data): array
    {
        $token = $this->generateToken();

        $id = $this->db->insert('offers', [
            'application_id' => $applicationId,
            'salary'         => isset($data['salary']) && $data['salary'] !== '' ? (float) $data['salary'] : null,
            'currency'       => $data['currency'] ?? 'USD',
            'start_date'     => $this->dateOrNull($data['start_date'] ?? null),
            'expiry_date'    => $this->dateOrNull($data['expiry_date'] ?? null),
            'status'         => 'draft',
            'token'          => $token,
            'notes'          => $data['notes'] ?? null,
        ]);

        return $this->getOffer($id) ?? ['id' => $id, 'application_id' => $applicationId, 'token' => $token];
    }

    /**
     * Update an offer's editable fields.
     *
     * @param array<string,mixed> $data
     */
    public function updateOffer(int $id, array $data): ?array
    {
        $allowed = ['salary', 'currency', 'start_date', 'expiry_date', 'notes', 'status'];
        $update = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            if ($field === 'salary') {
                $update[$field] = $data[$field] !== '' ? (float) $data[$field] : null;
            } elseif (in_array($field, ['start_date', 'expiry_date'], true)) {
                $update[$field] = $this->dateOrNull($data[$field]);
            } else {
                $update[$field] = $data[$field];
            }
        }
        if (!empty($update)) {
            $this->db->update('offers', $update, ['id' => $id]);
        }
        return $this->getOffer($id);
    }

    /**
     * Mark an offer as sent and build its letter.
     *
     * @return array{sent:bool,letter:string,offer:array<string,mixed>}
     */
    public function sendOffer(int $id): array
    {
        $offer = $this->getOffer($id);
        if ($offer === null) {
            throw new \RuntimeException('Offer not found');
        }

        $this->db->update('offers', ['status' => 'sent'], ['id' => $id]);

        // Advance the application into the offer stage.
        $this->db->update(
            'applications',
            ['pipeline_stage' => 'offer'],
            ['id' => (int) $offer['application_id']]
        );

        $letter = $this->generateOfferLetter($id);
        $this->pretendSendEmail($offer, $letter);

        $offer = $this->getOffer($id);
        return ['sent' => true, 'letter' => $letter, 'offer' => $offer];
    }

    /**
     * Record a candidate's response to an offer by token.
     *
     * @return array<string,mixed>|null the updated offer
     */
    public function processResponse(string $token, bool $accepted): ?array
    {
        $offer = $this->findByToken($token);
        if ($offer === null) {
            return null;
        }

        // Guard against responding to a withdrawn/expired offer.
        if (in_array($offer['status'], ['accepted', 'rejected', 'expired'], true)) {
            return $this->getOffer((int) $offer['id']);
        }

        $status = $accepted ? 'accepted' : 'rejected';
        $this->db->update('offers', ['status' => $status], ['id' => (int) $offer['id']]);

        if ($accepted) {
            $this->db->update(
                'applications',
                ['pipeline_stage' => 'hired', 'status' => 'hired'],
                ['id' => (int) $offer['application_id']]
            );
        }

        return $this->getOffer((int) $offer['id']);
    }

    /**
     * Build an HTML offer letter for an offer id.
     */
    public function generateOfferLetter(int $offerId): string
    {
        $row = $this->db->fetch(
            'SELECT o.*, a.job_id, a.candidate_id,
                    j.title AS job_title, j.location AS job_location,
                    t.name AS company_name,
                    c.first_name, c.last_name, c.email AS candidate_email
                FROM offers o
                INNER JOIN applications a ON a.id = o.application_id
                INNER JOIN jobs j ON j.id = a.job_id
                INNER JOIN candidates c ON c.id = a.candidate_id
                LEFT JOIN tenants t ON t.id = j.tenant_id
                WHERE o.id = :id LIMIT 1',
            [':id' => $offerId]
        );
        if ($row === null) {
            return '<p>Offer not found.</p>';
        }

        $candidateName = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        $candidateName = $candidateName !== '' ? $candidateName : 'Candidate';
        $jobTitle = (string) ($row['job_title'] ?? 'the position');
        $company = (string) ($row['company_name'] ?? 'the Company');
        $currency = (string) ($row['currency'] ?? 'USD');
        $salary = $row['salary'] !== null ? number_format((float) $row['salary'], 2) : 'to be discussed';
        $startDate = !empty($row['start_date']) ? date('F j, Y', strtotime((string) $row['start_date'])) : 'a mutually agreed date';
        $location = (string) ($row['job_location'] ?? '');
        $expiry = !empty($row['expiry_date']) ? date('F j, Y', strtotime((string) $row['expiry_date'])) : '';
        $today = date('F j, Y');

        $safeName = htmlspecialchars($candidateName, ENT_QUOTES, 'UTF-8');
        $safeJob = htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8');
        $safeCompany = htmlspecialchars($company, ENT_QUOTES, 'UTF-8');
        $safeSalary = htmlspecialchars($currency . ' ' . $salary, ENT_QUOTES, 'UTF-8');
        $safeStart = htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8');
        $safeLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
        $safeExpiry = htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8');
        $notes = !empty($row['notes']) ? '<p style="margin:16px 0;font-size:14px;line-height:1.6;">'
            . nl2br(htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8')) . '</p>' : '';

        $locationLine = $safeLocation !== ''
            ? "<li><strong>Location:</strong> {$safeLocation}</li>"
            : '';
        $expiryLine = $safeExpiry !== ''
            ? "<p style=\"font-size:13px;color:#6b7280;\">This offer remains valid until <strong>{$safeExpiry}</strong>.</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="margin:0;background:#f4f4f7;font-family:Georgia,'Times New Roman',serif;color:#1f2937;">
  <div style="max-width:640px;margin:0 auto;padding:32px;">
    <div style="background:#ffffff;border-radius:8px;padding:40px;border:1px solid #e5e7eb;">
      <p style="text-align:right;font-size:13px;color:#6b7280;margin:0 0 24px;">{$today}</p>
      <h1 style="font-size:22px;margin:0 0 8px;">Offer of Employment</h1>
      <h2 style="font-size:16px;font-weight:normal;color:#6b7280;margin:0 0 24px;">{$safeCompany}</h2>
      <p style="font-size:14px;line-height:1.6;">Dear {$safeName},</p>
      <p style="font-size:14px;line-height:1.6;">
        We are delighted to offer you the position of <strong>{$safeJob}</strong> at {$safeCompany}.
        We were impressed throughout the process and believe you will be a valuable addition to our team.
      </p>
      <ul style="font-size:14px;line-height:1.8;">
        <li><strong>Position:</strong> {$safeJob}</li>
        <li><strong>Annual Compensation:</strong> {$safeSalary}</li>
        <li><strong>Proposed Start Date:</strong> {$safeStart}</li>
        {$locationLine}
      </ul>
      {$notes}
      {$expiryLine}
      <p style="font-size:14px;line-height:1.6;">
        We look forward to welcoming you on board. Please use the link in your email to formally accept
        or decline this offer.
      </p>
      <p style="font-size:14px;line-height:1.6;margin-top:24px;">Sincerely,<br>The {$safeCompany} Hiring Team</p>
    </div>
  </div>
</body>
</html>
HTML;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(24));
        } while ($this->findByToken($token) !== null);
        return $token;
    }

    private function dateOrNull($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string) $value);
        return $ts !== false ? date('Y-m-d', $ts) : null;
    }

    /**
     * @param array<string,mixed> $offer
     */
    private function pretendSendEmail(array $offer, string $letter): void
    {
        logger('Offer letter queued for application ' . ($offer['application_id'] ?? '?'), 'info');
    }
}
