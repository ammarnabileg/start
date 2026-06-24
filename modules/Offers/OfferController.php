<?php
namespace App\Modules\Offers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Core\Validator;

/**
 * HTTP controller for offers.
 *
 * Protected (auth + offers.*): index, show, create, store, update, send.
 * Public (token, NO auth): accept, reject — how a candidate responds.
 */
class OfferController
{
    private OfferService $service;
    private Auth $auth;
    private Request $request;
    private Database $db;

    public function __construct(?OfferService $service = null)
    {
        $this->service = $service ?? new OfferService();
        $this->auth = new Auth();
        $this->request = new Request();
        $this->db = Database::instance();
    }

    public function index(array $params = []): void
    {
        $this->auth->requirePermission('offers.view');
        $tenantId = $this->tenantId();

        $filters = array_filter([
            'status' => $this->request->get('status'),
        ], static fn($v) => $v !== null && $v !== '');

        $offers = $this->service->getOffers($tenantId, $filters);

        if ($this->wantsJson()) {
            Response::success(['offers' => $offers, 'filters' => $filters]);
            return;
        }
        Response::view('hr.offers.index', ['offers' => $offers, 'filters' => $filters]);
    }

    public function show(array $params = []): void
    {
        $this->auth->requirePermission('offers.view');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $offer = $this->service->getOfferForTenant($id, $tenantId);
        if ($offer === null) {
            Response::error('Offer not found', 404);
            return;
        }
        $letter = $this->service->generateOfferLetter($id);

        if ($this->wantsJson()) {
            Response::success(['offer' => $offer, 'letter' => $letter]);
            return;
        }
        Response::view('hr.offers.show', ['offer' => $offer, 'letter' => $letter]);
    }

    /**
     * Render the offer creation form for an application.
     */
    public function create(array $params = []): void
    {
        $this->auth->requirePermission('offers.create');
        $applicationId = (int) ($params['id'] ?? $params['applicationId'] ?? $this->request->get('application_id', 0));

        if ($this->wantsJson()) {
            Response::success(['application_id' => $applicationId]);
            return;
        }
        Response::view('hr.offers.create', ['application_id' => $applicationId]);
    }

    /**
     * Persist a new draft offer.
     */
    public function store(array $params = []): void
    {
        $this->auth->requirePermission('offers.create');
        $tenantId = $this->tenantId();
        $data = $this->request->all();

        $applicationId = (int) ($data['application_id'] ?? 0);
        if ($applicationId <= 0) {
            Response::error('application_id is required', 422);
            return;
        }

        [$ok, $errors] = (new Validator())->validate($data, [
            'salary' => 'numeric',
        ]);
        if (!$ok) {
            Response::error('Validation failed', 422, $errors);
            return;
        }

        // Ensure the application belongs to this tenant.
        $application = $this->db->fetch(
            'SELECT a.* FROM applications a INNER JOIN jobs j ON j.id = a.job_id
                WHERE a.id = :id AND j.tenant_id = :tid LIMIT 1',
            [':id' => $applicationId, ':tid' => $tenantId]
        );
        if ($application === null) {
            Response::error('Application not found', 404);
            return;
        }

        $offer = $this->service->createOffer($applicationId, [
            'salary'      => $data['salary'] ?? null,
            'currency'    => $data['currency'] ?? 'USD',
            'start_date'  => $data['start_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'notes'       => $data['notes'] ?? null,
        ]);

        Response::success(['offer' => $offer], 'Offer created', 201);
    }

    /**
     * Update an existing offer.
     */
    public function update(array $params = []): void
    {
        $this->auth->requirePermission('offers.create');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $existing = $this->service->getOfferForTenant($id, $tenantId);
        if ($existing === null) {
            Response::error('Offer not found', 404);
            return;
        }

        $data = $this->request->all();
        if (array_key_exists('salary', $data) && $data['salary'] !== '' && $data['salary'] !== null) {
            [$ok, $errors] = (new Validator())->validate($data, ['salary' => 'numeric']);
            if (!$ok) {
                Response::error('Validation failed', 422, $errors);
                return;
            }
        }

        $offer = $this->service->updateOffer($id, $data);
        Response::success(['offer' => $offer], 'Offer updated');
    }

    /**
     * Send an offer to the candidate.
     */
    public function send(array $params = []): void
    {
        $this->auth->requirePermission('offers.send');
        $id = (int) ($params['id'] ?? 0);
        $tenantId = $this->tenantId();

        $existing = $this->service->getOfferForTenant($id, $tenantId);
        if ($existing === null) {
            Response::error('Offer not found', 404);
            return;
        }

        try {
            $result = $this->service->sendOffer($id);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
            return;
        }

        Response::success([
            'offer'  => $result['offer'],
            'sent'   => $result['sent'],
            'letter' => $result['letter'],
        ], 'Offer sent');
    }

    // ------------------------------------------------------------------
    // Public (token) actions — NO auth
    // ------------------------------------------------------------------

    /**
     * PUBLIC: candidate accepts an offer by token.
     */
    public function accept(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        $offer = $this->service->processResponse($token, true);
        if ($offer === null) {
            $this->respondPublic('Offer not found', 404, null, false);
            return;
        }
        $this->respondPublic('Thank you for accepting the offer. We are thrilled to have you on board!', 200, $offer, true);
    }

    /**
     * PUBLIC: candidate rejects an offer by token.
     */
    public function reject(array $params = []): void
    {
        $token = (string) ($params['token'] ?? '');
        $offer = $this->service->processResponse($token, false);
        if ($offer === null) {
            $this->respondPublic('Offer not found', 404, null, false);
            return;
        }
        $this->respondPublic('You have declined this offer. Thank you for your time and consideration.', 200, $offer, true);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function respondPublic(string $message, int $status, ?array $offer, bool $accepted): void
    {
        if ($this->wantsJson()) {
            if ($status >= 400) {
                Response::error($message, $status);
                return;
            }
            Response::success(['offer' => $offer, 'message' => $message], $message, $status);
            return;
        }
        // Server-rendered thank-you page (degrades to inline message if absent).
        Response::view('offers.response', [
            'message'  => $message,
            'offer'    => $offer,
            'accepted' => $accepted,
            'status'   => $status,
        ]);
    }

    private function tenantId(): int
    {
        (new Tenant())->resolve();
        $tenantId = (new Tenant())->currentId();
        if ($tenantId === null) {
            $user = $this->auth->user();
            $tenantId = $user && $user['tenant_id'] !== null ? (int) $user['tenant_id'] : 0;
        }
        if ($tenantId > 0) {
            $this->db->setTenantId($tenantId);
        }
        return (int) $tenantId;
    }

    private function wantsJson(): bool
    {
        return $this->request->isAjax()
            || str_contains((string) $this->request->header('Accept'), 'application/json')
            || $this->request->bearerToken() !== null;
    }
}
