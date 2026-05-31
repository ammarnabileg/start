<?php
/**
 * api/wallet.php — Wallet API (deposit, withdraw, balance)
 * All POST actions require CSRF + authentication.
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Login required']); exit;
}

$current_user = get_auth_user();
$uid = (int)$current_user['id'];

$method = $_SERVER['REQUEST_METHOD'];

// GET action: balance + transactions
if ($method === 'GET') {
    $action = $_GET['action'] ?? 'balance';
    if ($action !== 'balance') {
        echo json_encode(['error' => 'Invalid action']); exit;
    }
    try {
        $user = db_fetch('SELECT wallet_balance FROM users WHERE id = ?', [$uid]);
        $txns = db_fetch_all(
            'SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
            [$uid]
        );
        echo json_encode([
            'success' => true,
            'balance' => number_format((float)($user['wallet_balance'] ?? 0), 2, '.', ''),
            'transactions' => $txns,
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load balance']);
    }
    exit;
}

// POST actions
if ($method !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

if (!verify_csrf($data['csrf_token'] ?? '')) {
    echo json_encode(['error' => 'Invalid CSRF token']); exit;
}

$action = $data['action'] ?? '';

// ─── DEPOSIT ─────────────────────────────────────────────────────────────────
if ($action === 'deposit') {
    $amount = (float)($data['amount'] ?? 0);

    if ($amount <= 0 || $amount > 10000) {
        echo json_encode(['error' => 'Amount must be between 1 and 10,000 SAR']); exit;
    }
    if (!is_numeric($data['amount'] ?? '')) {
        echo json_encode(['error' => 'Invalid amount']); exit;
    }

    // Rate limit: max 5 deposits per hour
    try {
        $recent = db_fetch(
            "SELECT COUNT(*) as cnt FROM wallet_transactions WHERE user_id = ? AND type = 'deposit' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$uid]
        );
        if ((int)($recent['cnt'] ?? 0) >= 5) {
            echo json_encode(['error' => 'Rate limit exceeded: max 5 deposits per hour']); exit;
        }
    } catch (Exception $e) {
        echo json_encode(['error' => 'Rate limit check failed']); exit;
    }

    // DB transaction
    try {
        $pdo = get_pdo();
        $pdo->beginTransaction();

        // Atomic update and get new balance
        $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?')->execute([$amount, $uid]);
        $newBal = (float)$pdo->query("SELECT wallet_balance FROM users WHERE id = {$uid} FOR UPDATE")->fetchColumn();

        // Insert transaction log
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, amount, type, description, balance_after) VALUES (?,?,?,?,?)"
        )->execute([$uid, $amount, 'deposit', 'Wallet top-up of ' . number_format($amount, 2) . ' SAR', $newBal]);

        $pdo->commit();
        echo json_encode(['success' => true, 'balance' => number_format($newBal, 2, '.', ''), 'message' => 'Deposit successful']);
    } catch (Exception $e) {
        try { get_pdo()->rollBack(); } catch (Exception $e2) {}
        error_log('Wallet deposit error: ' . $e->getMessage());
        echo json_encode(['error' => 'Deposit failed. Please try again.']);
    }
    exit;
}

// ─── WITHDRAW ────────────────────────────────────────────────────────────────
if ($action === 'withdraw') {
    $amount = (float)($data['amount'] ?? 0);

    if ($amount <= 0) {
        echo json_encode(['error' => 'Amount must be greater than 0']); exit;
    }

    try {
        $pdo = get_pdo();
        $pdo->beginTransaction();

        // Lock user row to prevent race conditions
        $user = $pdo->query("SELECT wallet_balance FROM users WHERE id = {$uid} FOR UPDATE")->fetch(PDO::FETCH_ASSOC);
        $balance = (float)($user['wallet_balance'] ?? 0);

        if ($balance < $amount) {
            $pdo->rollBack();
            echo json_encode([
                'error' => 'Insufficient balance',
                'balance' => number_format($balance, 2, '.', ''),
            ]); exit;
        }

        $newBal = $balance - $amount;

        $pdo->prepare('UPDATE users SET wallet_balance = ? WHERE id = ?')->execute([$newBal, $uid]);
        $pdo->prepare(
            "INSERT INTO wallet_transactions (user_id, amount, type, description, balance_after) VALUES (?,?,?,?,?)"
        )->execute([$uid, -$amount, 'withdrawal', 'Withdrawal of ' . number_format($amount, 2) . ' SAR', $newBal]);

        $pdo->commit();
        echo json_encode(['success' => true, 'balance' => number_format($newBal, 2, '.', ''), 'message' => 'Withdrawal processed']);
    } catch (Exception $e) {
        try { get_pdo()->rollBack(); } catch (Exception $e2) {}
        error_log('Wallet withdraw error: ' . $e->getMessage());
        echo json_encode(['error' => 'Withdrawal failed. Please try again.']);
    }
    exit;
}

// ─── BALANCE (POST) ──────────────────────────────────────────────────────────
if ($action === 'balance') {
    try {
        $user = db_fetch('SELECT wallet_balance FROM users WHERE id = ?', [$uid]);
        $txns = db_fetch_all(
            'SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20',
            [$uid]
        );
        echo json_encode([
            'success' => true,
            'balance' => number_format((float)($user['wallet_balance'] ?? 0), 2, '.', ''),
            'transactions' => $txns,
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to load balance']);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action']);
