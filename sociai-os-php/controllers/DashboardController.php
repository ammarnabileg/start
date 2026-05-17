<?php
/**
 * SociAI OS - Dashboard Controller
 */

declare(strict_types=1);

namespace SociAI\Controllers;

use SociAI\Core\{Auth, Request, Response};
use SociAI\Models\{User, Brand, Analytics};

class DashboardController
{
    private Response  $response;
    private Analytics $analytics;
    private User      $userModel;
    private Brand     $brandModel;

    public function __construct()
    {
        $this->response   = new Response();
        $this->analytics  = new Analytics();
        $this->userModel  = new User();
        $this->brandModel = new Brand();
    }

    public function index(array $params): void
    {
        Auth::requireAuth();
        $user   = Auth::getCurrentUser();
        $brands = $this->userModel->getBrands($user['id']);

        // Pick active brand (from session or first brand)
        $activeBrandId = $_SESSION['active_brand_id'] ?? ($brands[0]['id'] ?? null);
        $dashData      = null;
        $activeBrand   = null;

        if ($activeBrandId) {
            $activeBrand = $this->brandModel->find($activeBrandId);
            if ($activeBrand) {
                $dashData = $this->analytics->getDashboard($activeBrandId, '30d');
            }
        }

        $notifCount = $this->userModel->getUnreadCount($user['id']);
        $userStats  = $this->userModel->getStats($user['id']);

        $this->response->view('dashboard.index', [
            'user'          => $this->userModel->sanitize($user),
            'brands'        => $brands,
            'activeBrand'   => $activeBrand,
            'dashData'      => $dashData,
            'notifCount'    => $notifCount,
            'userStats'     => $userStats,
            'pageTitle'     => 'Dashboard',
            'layout'        => 'app',
        ]);
    }
}
