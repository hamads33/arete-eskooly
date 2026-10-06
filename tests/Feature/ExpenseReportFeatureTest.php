<?php
declare(strict_types=1);

use App\Core\App;
use App\Repositories\AccountRepository;

/** Expense Report date-range, account head and search filters. */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['expense report: ' . $n] = $v; };
    $db = $app->database();
    $institute = (int) $db->query('SELECT id FROM institutes ORDER BY id LIMIT 1')->fetchColumn();
    $admin = (int) $db->query('SELECT u.id FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" LIMIT 1')->fetchColumn();
    $suffix = substr(bin2hex(random_bytes(4)), 0, 8);
    $r = new AccountRepository($db);
    $heads = [];

    try {
        $r->addHead($institute, 'Utilities ' . $suffix, 'expense');
        $headId = (int) $db->query('SELECT id FROM account_heads WHERE institute_id=:i AND name=:n', ['i' => $institute, 'n' => 'Utilities ' . $suffix])->fetchColumn();
        $heads[] = $headId;

        $old = $r->create($institute, 'expense', '2019-01-05', 'Old expense ' . $suffix, 1000, $admin, null, $headId);
        $recent = $r->create($institute, 'expense', date('Y-m-d'), 'Recent expense ' . $suffix, 2500, $admin, 'REF-' . $suffix, $headId);

        $inRange = array_column($r->expensesInRange($institute, date('Y-m-01'), date('Y-m-d')), 'id', 'id');
        $check('a range covering only this month excludes an old entry', isset($inRange[$recent]) && !isset($inRange[$old]));

        $wide = array_column($r->expensesInRange($institute, '2019-01-01', date('Y-m-d')), 'id', 'id');
        $check('a wide range includes both entries', isset($wide[$old], $wide[$recent]));

        $byHead = $r->expensesInRange($institute, '2019-01-01', date('Y-m-d'), $headId);
        $check('the account head filter narrows to that head', count($byHead) === 2 && $byHead[0]['head_name'] === 'Utilities ' . $suffix);

        $bySearch = $r->expensesInRange($institute, '2019-01-01', date('Y-m-d'), 0, 'REF-' . $suffix);
        $check('the search filter matches on reference', count($bySearch) === 1 && (int) $bySearch[0]['id'] === $recent);

        $months = $r->expenseMonths($institute);
        $check('expenseMonths lists a month that has an expense', in_array(date('Y-m'), $months, true) && in_array('2019-01', $months, true));
    } finally {
        $db->query('DELETE FROM account_transactions WHERE institute_id=:i AND description LIKE :d', ['i' => $institute, 'd' => '%' . $suffix]);
        foreach ($heads as $h) {
            $db->query('DELETE FROM account_heads WHERE id=:id', ['id' => $h]);
        }
    }
    return $out;
};
