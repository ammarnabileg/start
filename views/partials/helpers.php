<?php
/**
 * views/partials/helpers.php
 * Shared PHP helper functions for views.
 */

if (!function_exists('flash')) {
    /**
     * Get and remove a flash value from the session.
     */
    function flash(string $key): mixed
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
}

if (!function_exists('setFlash')) {
    /**
     * Store a flash value in the session for the next request.
     */
    function setFlash(string $key, mixed $val): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['_flash'][$key] = $val;
    }
}

if (!function_exists('ago')) {
    /**
     * Return a human-readable "time ago" string from a datetime string.
     * e.g. "2 hours ago", "just now"
     */
    function ago(string $datetime): string
    {
        $time = strtotime($datetime);
        if ($time === false) return $datetime;

        $diff = time() - $time;

        if ($diff < 0) {
            // Future
            $diff = abs($diff);
            if ($diff < 60)      return 'in a few seconds';
            if ($diff < 3600)    return 'in ' . round($diff / 60) . ' minute' . (round($diff / 60) === 1 ? '' : 's');
            if ($diff < 86400)   return 'in ' . round($diff / 3600) . ' hour' . (round($diff / 3600) === 1 ? '' : 's');
            if ($diff < 2592000) return 'in ' . round($diff / 86400) . ' day' . (round($diff / 86400) === 1 ? '' : 's');
            return 'in ' . round($diff / 2592000) . ' month' . (round($diff / 2592000) === 1 ? '' : 's');
        }

        if ($diff < 5)       return 'just now';
        if ($diff < 60)      return $diff . ' second' . ($diff === 1 ? '' : 's') . ' ago';
        if ($diff < 3600)    { $m = round($diff / 60);   return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago'; }
        if ($diff < 86400)   { $h = round($diff / 3600); return $h . ' hour' . ($h === 1 ? '' : 's') . ' ago'; }
        if ($diff < 2592000) { $d = round($diff / 86400); return $d . ' day' . ($d === 1 ? '' : 's') . ' ago'; }
        if ($diff < 31536000){ $mo = round($diff / 2592000); return $mo . ' month' . ($mo === 1 ? '' : 's') . ' ago'; }
        $y = round($diff / 31536000);
        return $y . ' year' . ($y === 1 ? '' : 's') . ' ago';
    }
}

if (!function_exists('formatScore')) {
    /**
     * Return color-coded HTML score display.
     * Score is expected 0–100 (or 0.0–1.0, auto-detected).
     */
    function formatScore(float $score): string
    {
        // Normalise 0–1 to 0–100
        if ($score <= 1.0 && $score >= 0) {
            $score = $score * 100;
        }
        $score = (int) round($score);

        if ($score >= 80) {
            $color = '#22c55e'; $bg = 'rgba(34,197,94,0.12)'; $label = 'Excellent';
        } elseif ($score >= 60) {
            $color = '#4f46e5'; $bg = 'rgba(79,70,229,0.12)'; $label = 'Good';
        } elseif ($score >= 40) {
            $color = '#f59e0b'; $bg = 'rgba(245,158,11,0.12)'; $label = 'Fair';
        } else {
            $color = '#ef4444'; $bg = 'rgba(239,68,68,0.12)'; $label = 'Low';
        }

        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:6px;background:%s;border:1px solid %s;border-radius:6px;padding:3px 10px;">'
            . '<span style="font-weight:700;color:%s;font-size:0.9rem;">%d%%</span>'
            . '<span style="font-size:0.7rem;color:%s;opacity:0.85;">%s</span>'
            . '</span>',
            $bg, $color . '33', $color, $score, $color, $label
        );
    }
}

if (!function_exists('recommendationBadge')) {
    /**
     * Return an HTML badge for an AI recommendation.
     * Common values: 'strongly_recommend', 'recommend', 'neutral', 'not_recommend', 'reject'
     */
    function recommendationBadge(string $rec): string
    {
        $map = [
            'strongly_recommend' => ['Strongly Recommend', '#22c55e', 'rgba(34,197,94,0.12)', '⭐'],
            'recommend'          => ['Recommend',          '#4f46e5', 'rgba(79,70,229,0.12)',  '✓'],
            'neutral'            => ['Neutral',            '#94a3b8', 'rgba(148,163,184,0.1)', '~'],
            'not_recommend'      => ['Not Recommend',      '#f59e0b', 'rgba(245,158,11,0.12)', '⚠'],
            'reject'             => ['Reject',             '#ef4444', 'rgba(239,68,68,0.12)',  '✕'],
        ];

        $key = strtolower(str_replace([' ', '-'], '_', $rec));
        [$label, $color, $bg, $icon] = $map[$key] ?? [ucwords(str_replace('_',' ',$rec)), '#64748b', 'rgba(100,116,139,0.1)', '?'];

        return sprintf(
            '<span style="display:inline-flex;align-items:center;gap:5px;background:%s;border:1px solid %s;'
            . 'border-radius:20px;padding:3px 12px;font-size:0.8rem;font-weight:600;color:%s;white-space:nowrap;">'
            . '%s %s</span>',
            $bg, $color . '44', $color, $icon, htmlspecialchars($label)
        );
    }
}

if (!function_exists('statusBadge')) {
    /**
     * Return an HTML badge for an application/candidate status.
     * Common values: applied, screening, interview, offer, hired, rejected, withdrawn
     */
    function statusBadge(string $status): string
    {
        $map = [
            'applied'    => ['Applied',    '#94a3b8', 'rgba(148,163,184,0.1)'],
            'screening'  => ['Screening',  '#4f46e5', 'rgba(79,70,229,0.12)'],
            'interview'  => ['Interview',  '#7c3aed', 'rgba(124,58,237,0.12)'],
            'assessment' => ['Assessment', '#06b6d4', 'rgba(6,182,212,0.12)'],
            'offer'      => ['Offer',      '#f59e0b', 'rgba(245,158,11,0.12)'],
            'hired'      => ['Hired',      '#22c55e', 'rgba(34,197,94,0.12)'],
            'rejected'   => ['Rejected',   '#ef4444', 'rgba(239,68,68,0.12)'],
            'withdrawn'  => ['Withdrawn',  '#64748b', 'rgba(100,116,139,0.1)'],
            'on_hold'    => ['On Hold',    '#f97316', 'rgba(249,115,22,0.12)'],
        ];

        $key = strtolower(str_replace([' ', '-'], '_', $status));
        [$label, $color, $bg] = $map[$key] ?? [ucwords(str_replace('_', ' ', $status)), '#94a3b8', 'rgba(148,163,184,0.1)'];

        return sprintf(
            '<span style="display:inline-block;background:%s;border:1px solid %s;'
            . 'border-radius:20px;padding:3px 12px;font-size:0.78rem;font-weight:600;color:%s;white-space:nowrap;">'
            . '%s</span>',
            $bg, $color . '44', $color, htmlspecialchars($label)
        );
    }
}

if (!function_exists('avatar_initials')) {
    /**
     * Return 1–2 uppercase initials from a full name.
     * "John Doe" -> "JD", "Alice" -> "AL"
     */
    function avatar_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') return '??';
        $parts = array_values(array_filter(explode(' ', $name)));
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0], 0, 2));
        }
        return strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
    }
}

if (!function_exists('formatCurrency')) {
    /**
     * Format a float as a currency string.
     * e.g. formatCurrency(75000.0) -> "$75,000.00"
     *      formatCurrency(1500.5, 'EUR') -> "€1,500.50"
     */
    function formatCurrency(float $amount, string $currency = 'USD'): string
    {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£',
            'JPY' => '¥', 'CAD' => 'CA$', 'AUD' => 'A$',
            'CHF' => 'CHF ', 'INR' => '₹', 'AED' => 'AED ',
        ];
        $symbol = $symbols[strtoupper($currency)] ?? strtoupper($currency) . ' ';
        $decimals = in_array(strtoupper($currency), ['JPY']) ? 0 : 2;
        return $symbol . number_format($amount, $decimals);
    }
}

if (!function_exists('truncate')) {
    /**
     * Truncate text to a maximum length, appending ellipsis if needed.
     */
    function truncate(string $text, int $len = 100): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $len) return $text;
        return rtrim(mb_substr($text, 0, $len)) . '…';
    }
}
