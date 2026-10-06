<?php
// Shared view helpers for the SMS Center pages.
if (!function_exists('sms_status_badge')) {
    function sms_status_badge(string $status): string
    {
        $labels = ['queued' => 'Queued', 'scheduled' => 'Scheduled', 'reserved' => 'Reserved', 'processing' => 'Sending', 'sent' => 'Sent',
            'failed' => 'Failed', 'cancelled' => 'Cancelled', 'connected' => 'Connected', 'offline' => 'Offline', 'pending' => 'Not paired', 'disabled' => 'Disabled'];
        return '<span class="status-badge sms-badge sms-badge--' . e($status) . '">' . e($labels[$status] ?? ucfirst($status)) . '</span>';
    }

    function sms_label(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $value));
    }

    function sms_time(?string $value): string
    {
        return $value ? e(date('d M Y, h:i A', strtotime($value))) : '<span class="sms-muted">—</span>';
    }

    /** Previous/next pager in the style the LMS lists already use. */
    function sms_pager(int $page, int $perPage, int $total, array $query): string
    {
        $last = max(1, (int) ceil($total / $perPage));
        if ($last <= 1) {
            return $total ? '<p class="sms-pager__info">' . $total . ' record' . ($total === 1 ? '' : 's') . '</p>' : '';
        }
        $link = static fn(int $p) => '?' . http_build_query(array_filter($query + ['page' => $p], static fn($v) => $v !== '' && $v !== 0 && $v !== null));
        $html = '<div class="sms-pager"><p class="sms-pager__info">Page ' . $page . ' of ' . $last . ' · ' . $total . ' records</p><div class="filter-actions">';
        if ($page > 1) {
            $html .= '<a class="btn btn--secondary" href="' . e($link($page - 1)) . '">Previous</a>';
        }
        if ($page < $last) {
            $html .= '<a class="btn btn--secondary" href="' . e($link($page + 1)) . '">Next</a>';
        }
        return $html . '</div></div>';
    }
}
