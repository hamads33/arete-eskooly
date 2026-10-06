<?php
declare(strict_types=1);

use App\Core\{App, CSRF, Request};
use App\Repositories\{AcademicRepository, FeeRepository, SettingsRepository, StudentRepository};
use App\Services\{FeeService, StudentService};

/**
 * eSkooly-style Collect Fees: preview before an invoice exists, editable particulars + fine +
 * discount until the first payment, live totals, deposit limits, previous balance carry-over,
 * the paid receipt page and the typeahead search — positive, negative and edge cases.
 */
return static function (App $app): array {
    $out = [];
    $check = static function (string $n, bool $v) use (&$out): void { $out['collect fees: ' . $n] = $v; };
    $db = $app->database();
    $i = (int) (new SettingsRepository($db))->institute()['id'];
    $u = $db->query('SELECT u.id,u.name,u.email,u.status FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug="admin" ORDER BY u.id LIMIT 1')->fetch();
    $app->session()->set('auth_user', $u + ['roles' => ['admin'], 'permissions' => ['*']]);
    $suffix = bin2hex(random_bytes(4));
    $get = static function (string $uri) use ($app) { parse_str((string) parse_url($uri, PHP_URL_QUERY), $q); return $app->run(new Request($q, [], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri])); };
    $post = static fn(array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/fees/payment']));
    $loc = static fn($res): string => (string) ($res->headers()['Location'] ?? '');
    $academic = new AcademicRepository($db);
    $className = 'Collect QA ' . $suffix;
    $academic->createClass($i, $className, 5000, null);
    $class = (int) $db->query('SELECT id FROM classes WHERE institute_id=:i AND name=:n', ['i' => $i, 'n' => $className])->fetchColumn();
    $students = new StudentRepository($db);
    $svcStudents = new StudentService($students);
    $fees = new FeeRepository($db);
    $service = new FeeService($fees, $students);
    $ids = [];
    $mk = static function (string $name, string $reg, float $discount) use ($svcStudents, $students, $i, $class, &$ids): array {
        $d = $svcStudents->data(['studentname' => $name, 'reg' => $reg, 'doa' => date('Y-m-d'), 'class' => $class, 'discount' => $discount, 'fname' => 'QA Father'], $i);
        $d['created_at'] = $d['updated_at'];
        $ids[] = $id = $students->create($d);
        return $students->find($id, $i);
    };
    try {
        $ali = $mk('Collect Ali ' . $suffix, 'CQ-' . $suffix . '-1', 0);
        $sara = $mk('Collect Sara ' . $suffix, 'CQ-' . $suffix . '-2', 10);
        $m1 = '2019-03'; $m2 = '2019-04';

        // ---- typeahead + landing ---------------------------------------------------------------
        $landing = $get('/admin/fees/collect');
        $check('landing shows eSkooly search card with tabs', $landing->status() === 200 && str_contains($landing->content(), 'Choose how you want to collect fees today') && str_contains($landing->content(), 'Student Wise'));
        $hint = $get('/admin/fees/collect?browse=1&student_query=' . rawurlencode('Collect Ali ' . $suffix) . '&month=' . $m1);
        $check('typing part of a name returns matching hints', str_contains($hint->content(), 'data-fee-results') && str_contains($hint->content(), 'CQ-' . $suffix . '-1') && !str_contains($hint->content(), 'CQ-' . $suffix . '-2'));
        $hint = $get('/admin/fees/collect?browse=1&student_query=' . rawurlencode('CQ-' . $suffix) . '&month=' . $m1);
        $check('typing a registration prefix returns every match', str_contains($hint->content(), 'CQ-' . $suffix . '-1') && str_contains($hint->content(), 'CQ-' . $suffix . '-2'));
        $none = $get('/admin/fees/collect?browse=1&student_query=zz-no-such-student-' . $suffix . '&month=' . $m1);
        $check('a search with no match says so', str_contains($none->content(), 'No students found'));
        $xss = $get('/admin/fees/collect?browse=1&student_query=' . rawurlencode('<script>x</script>') . '&month=' . $m1);
        $check('search text is escaped', !str_contains($xss->content(), '<script>x</script>'));

        // ---- preview has no side effects -------------------------------------------------------
        $page = $get('/admin/fees/collect?student_id=' . $ali['id'] . '&month=' . $m1);
        $html = $page->content();
        $check('selected student shows Fees Collection with info tiles', $page->status() === 200 && str_contains($html, 'Fees Collection') && str_contains($html, 'Guardian Name') && str_contains($html, 'QA Father'));
        $check('particulars are editable before any invoice exists', str_contains($html, 'name="item_amount[0]"') && str_contains($html, 'name="fine_amount"') && str_contains($html, 'name="discount_amount"'));
        $check('opening the screen does not create an invoice', $fees->invoice((int) $ali['id'], $m1) === null);
        $check('Total, Deposit and Due Balance are shown', str_contains($html, 'data-ek-total') && str_contains($html, 'data-ek-deposit') && str_contains($html, 'data-ek-due'));
        $preview = $service->preview($ali, $i, $m1);
        $check('preview uses class tuition', (float) $preview['lines'][0]['amount'] === 5000.0);
        $sp = $service->preview($sara, $i, $m1);
        $check('preview applies the student percentage discount', abs($sp['discount'] - round($sp['subtotal'] * 0.10, 2)) < 0.01 && str_contains($get('/admin/fees/collect?student_id=' . $sara['id'] . '&month=' . $m1)->content(), 'Student default: 10 %'));

        $labels = array_column($preview['lines'], 'particular');
        $amounts = array_column($preview['lines'], 'amount');
        $base = ['registration' => $ali['registration_no'], 'month' => $m1, 'payment_method' => 'Cash', 'item_label' => $labels];
        $total = array_sum($amounts);

        // ---- negative: nothing is written --------------------------------------------------------
        $bad = [
            'deposit above total' => ['item_amount' => $amounts, 'fine_amount' => '0', 'discount_amount' => '0', 'payment_amount' => (string) ($total + 1)],
            'zero deposit' => ['item_amount' => $amounts, 'fine_amount' => '0', 'discount_amount' => '0', 'payment_amount' => '0'],
            'negative particular' => ['item_amount' => array_replace($amounts, [0 => -100]), 'fine_amount' => '0', 'discount_amount' => '0', 'payment_amount' => '100'],
            'negative fine' => ['item_amount' => $amounts, 'fine_amount' => '-5', 'discount_amount' => '0', 'payment_amount' => '100'],
            'negative discount' => ['item_amount' => $amounts, 'fine_amount' => '0', 'discount_amount' => '-5', 'payment_amount' => '100'],
            'text amount' => ['item_amount' => array_replace($amounts, [0 => 'abc']), 'fine_amount' => '0', 'discount_amount' => '0', 'payment_amount' => '100'],
        ];
        foreach ($bad as $label => $body) {
            $res = $post($base + $body);
            $check($label . ' is refused and creates no invoice', $fees->invoice((int) $ali['id'], $m1) === null && str_contains($loc($res), '/admin/fees/collect?student_id=' . $ali['id']));
        }
        $res = $post(array_replace($base, ['item_amount' => $amounts, 'fine_amount' => '0', 'discount_amount' => '0', 'payment_amount' => '100', 'payment_method' => 'Bitcoin']));
        $check('an unknown payment method is refused', $res->status() === 422 && $fees->invoice((int) $ali['id'], $m1) === null);
        $res = $app->run(new Request([], $base + ['item_amount' => $amounts, 'payment_amount' => '100'], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/fees/payment']));
        $check('a payment without the CSRF token is refused', $res->status() === 419 && $fees->invoice((int) $ali['id'], $m1) === null);

        // ---- positive: edited particulars + fine + discount, partial deposit ---------------------
        $edited = array_replace($amounts, [0 => 4500]);
        $expectTotal = array_sum($edited) + 200 - 300;
        $res = $post($base + ['item_amount' => $edited, 'fine_amount' => '200', 'discount_amount' => '300', 'payment_amount' => '3000', 'payment_date' => date('Y-m-d', strtotime('-2 days')), 'reference' => 'REF-' . $suffix, 'remarks' => 'QA remark']);
        $inv = $fees->invoice((int) $ali['id'], $m1);
        $check('collecting creates the invoice with the edited amounts', $inv && abs((float) $inv['total_amount'] - $expectTotal) < 0.01 && (float) $inv['discount'] === 300.0);
        $items = array_column($fees->items((int) $inv['id']), 'amount', 'particular');
        $check('the fine is stored as its own particular', (float) ($items['Fine'] ?? 0) === 200.0 && (float) ($items['Monthly Tuition Fee'] ?? 0) === 4500.0);
        $check('zero-amount particulars are not stored', !in_array('0.00', array_map('strval', array_values($items)), true));
        $check('the invoice is partial with the right balance', $inv['status'] === 'partial' && abs((float) $inv['remaining_amount'] - ($expectTotal - 3000)) < 0.01);
        $payment = $fees->payments((int) $inv['id'])[0];
        $check('a back-dated payment keeps its date, reference and remarks', $payment['payment_date'] === date('Y-m-d', strtotime('-2 days')) && $payment['reference'] === 'REF-' . $suffix && $payment['remarks'] === 'QA remark');
        $check('after saving the cashier lands on the receipt', str_contains($loc($res), '/admin/fees/receipt/' . $payment['id']));

        $receipt = $get('/admin/fees/receipt/' . $payment['id']);
        $check('receipt shows Partially Paid with Total, Deposit and Remaining', $receipt->status() === 200 && str_contains($receipt->content(), 'Partially Paid') && str_contains($receipt->content(), 'Deposit Amount') && str_contains($receipt->content(), 'Remaining Balance'));
        $check('receipt offers Detailed and Mini slips plus WhatsApp', str_contains($receipt->content(), '/admin/reports/fee-receipt/' . $payment['id'] . '/pdf') && str_contains($receipt->content(), 'ek-mini-slip') && str_contains($receipt->content(), 'whatsapp'));

        // ---- admin can still edit every amount after a payment (eSkooly behaviour) ----------------
        $open = $get('/admin/fees/collect?student_id=' . $ali['id'] . '&month=' . $m1)->content();
        $check('after a payment an admin can still edit particulars, fine, discount and previous balance', str_contains($open, 'name="item_amount[0]"') && str_contains($open, 'name="fine_amount"') && str_contains($open, 'name="previous_balance"') && str_contains($open, 'name="discount_mode"') && str_contains($open, 'already paid'));
        $check('payment history keeps edit and reverse', str_contains($open, 'Payment History') && str_contains($open, '/payments/' . $payment['id'] . '/edit') && str_contains($open, '/payments/' . $payment['id'] . '/reverse'));
        $cur = $fees->invoice((int) $ali['id'], $m1);
        $paidSoFar = (float) $cur['paid_amount'];
        $bodyFor = static fn(array $amts, array $over = []) => array_replace($base, ['item_amount' => $amts, 'fine_amount' => '0', 'discount_mode' => 'amount', 'discount_amount' => '0', 'previous_balance' => '0', 'payment_amount' => '1'], $over);
        // negative: below already-paid, bad discount mode, >100 %, negative previous balance
        $post($bodyFor(array_fill(0, count($amounts), 0)));
        $check('a total below what is already paid is refused and nothing changes', $fees->invoice((int) $ali['id'], $m1) == $cur);
        $post($bodyFor($amounts, ['discount_mode' => 'magic']));
        $check('an unknown discount type is refused', $fees->invoice((int) $ali['id'], $m1) == $cur);
        $post($bodyFor($amounts, ['discount_mode' => 'percent', 'discount_amount' => '101']));
        $check('a discount above 100 % is refused', $fees->invoice((int) $ali['id'], $m1) == $cur);
        $post($bodyFor($amounts, ['previous_balance' => '-1']));
        $check('a negative previous balance is refused', $fees->invoice((int) $ali['id'], $m1) == $cur);
        $post($bodyFor($amounts, ['previous_balance' => 'x']));
        $check('a text previous balance is refused', $fees->invoice((int) $ali['id'], $m1) == $cur);
        // positive: raise tuition, set a previous balance and an amount discount, deposit 500
        $edited2 = array_replace($amounts, [0 => 6000]);
        $res = $post($bodyFor($edited2, ['previous_balance' => '700', 'discount_amount' => '250', 'payment_amount' => '500']));
        $adj = $fees->invoice((int) $ali['id'], $m1);
        $newTotal = array_sum($edited2) - 250 + 700;
        $check('editing after a payment recalculates the invoice and keeps earlier payments', abs((float) $adj['total_amount'] - $newTotal) < 0.01 && abs((float) $adj['paid_amount'] - ($paidSoFar + 500)) < 0.01 && abs((float) $adj['remaining_amount'] - ($newTotal - $paidSoFar - 500)) < 0.01 && (float) $adj['previous_balance'] === 700.0 && (float) $adj['discount'] === 250.0);
        $check('the earlier payment record is untouched', $fees->payment((int) $payment['id'], $i)['amount'] == $payment['amount']);
        $check('the invoice change is written to the audit log', (bool) $db->query("SELECT 1 FROM audit_logs WHERE action='fee.invoice_adjusted' AND entity_id=:id", ['id' => $adj['id']])->fetchColumn());
        // percentage discount from the screen
        $pctBase = array_sum($edited2);
        $post($bodyFor($edited2, ['previous_balance' => '700', 'discount_mode' => 'percent', 'discount_amount' => '10', 'payment_amount' => '1']));
        $adj = $fees->invoice((int) $ali['id'], $m1);
        $check('a percentage discount is converted to the right amount', abs((float) $adj['discount'] - round($pctBase * 0.10, 2)) < 0.01);
        // ---- the change shows up everywhere else ----------------------------------------------
        $fmtTotal = number_format((float) $adj['total_amount'], 2);
        $fmtRemain = number_format((float) $adj['remaining_amount'], 2);
        $check('the first receipt now shows the updated invoice total', str_contains($get('/admin/fees/receipt/' . $payment['id'])->content(), $fmtTotal));
        $check('the search hint shows the updated outstanding balance', str_contains($get('/admin/fees/collect?browse=1&student_query=' . rawurlencode('CQ-' . $suffix . '-1') . '&month=' . $m1)->content(), $fmtRemain));
        $rep = $get('/admin/fees/report?month=' . $m1)->content();
        $check('the fee report shows the updated total', str_contains($rep, $fmtTotal) || str_contains($rep, number_format((float) $adj['total_amount'])));
        $vouch = (new \App\Services\FeeVoucherService($db))->generate($i, $m1, [$ali], (int) $u['id']);
        $check('vouchers print the updated items and total', (float) $vouch[0]['invoice']['total_amount'] === (float) $adj['total_amount'] && in_array('6000.00', array_column($vouch[0]['items'], 'amount'), true));
        $owing = array_column($students->paginate($i, 'CQ-' . $suffix . '-1', null, 30, true), 'fee_remaining', 'id');
        $check('the pending-fees filter and defaulters use the new balance', abs((float) ($owing[$ali['id']] ?? -1) - (float) $adj['remaining_amount']) < 0.01);
        $after = $adj;
        $post(['registration' => $ali['registration_no'], 'month' => $m1, 'payment_method' => 'Cash', 'payment_amount' => (string) ((float) $after['remaining_amount'] + 0.01)]);
        $check('a deposit above the remaining balance is refused', (float) $fees->invoice((int) $ali['id'], $m1)['remaining_amount'] === (float) $after['remaining_amount']);
        $post(['registration' => $ali['registration_no'], 'month' => $m1, 'payment_method' => 'Cash', 'payment_amount' => $after['remaining_amount'], 'payment_date' => date('Y-m-d', strtotime('+3 days'))]);
        $paid = $fees->invoice((int) $ali['id'], $m1);
        $last = $fees->payments((int) $paid['id'])[0];
        $check('paying the exact remaining balance marks the invoice paid', $paid['status'] === 'paid' && (float) $paid['remaining_amount'] === 0.0);
        $check('a future payment date is ignored (today is used)', $last['payment_date'] === date('Y-m-d'));
        $check('receipt shows Fully Paid', str_contains($get('/admin/fees/receipt/' . $last['id'])->content(), 'Fully Paid'));
        $check('a fully paid month shows Paid in full and no deposit box', str_contains($get('/admin/fees/collect?student_id=' . $ali['id'] . '&month=' . $m1)->content(), 'Paid in full'));

        // ---- previous balance carries into the next month ------------------------------------------
        $saraFirst = $sp['lines'];
        $post(['registration' => $sara['registration_no'], 'month' => $m1, 'payment_method' => 'Cash', 'item_label' => array_column($saraFirst, 'particular'), 'item_amount' => array_column($saraFirst, 'amount'), 'fine_amount' => '0', 'discount_amount' => (string) $sp['discount'], 'payment_amount' => '1000']);
        $s1 = $fees->invoice((int) $sara['id'], $m1);
        $owed = (float) $s1['remaining_amount'];
        $next = $service->preview($sara, $i, $m2);
        $check('the unpaid balance shows as Previous Balance next month', abs($next['previous_balance'] - $owed) < 0.01 && $owed > 0);
        $check('previewing next month leaves last month untouched', (float) $fees->invoice((int) $sara['id'], $m1)['remaining_amount'] === $owed);
        $post(['registration' => $sara['registration_no'], 'month' => $m2, 'payment_method' => 'Cash', 'item_label' => array_column($next['lines'], 'particular'), 'item_amount' => array_column($next['lines'], 'amount'), 'fine_amount' => '0', 'discount_amount' => (string) $next['discount'], 'payment_amount' => (string) $next['total']]);
        $s2 = $fees->invoice((int) $sara['id'], $m2);
        $check('collecting next month in full clears the carried balance too', $s2 && $s2['status'] === 'paid' && abs((float) $s2['previous_balance'] - $owed) < 0.01 && (float) $fees->invoice((int) $sara['id'], $m1)['remaining_amount'] === 0.0);

        // ---- edge: discount larger than the fees, voided invoice --------------------------------
        $bilal = $mk('Collect Bilal ' . $suffix, 'CQ-' . $suffix . '-3', 0);
        $bp = $service->preview($bilal, $i, $m1);
        $res = $post(['registration' => $bilal['registration_no'], 'month' => $m1, 'payment_method' => 'Cash', 'item_label' => array_column($bp['lines'], 'particular'), 'item_amount' => array_column($bp['lines'], 'amount'), 'fine_amount' => '0', 'discount_amount' => '999999', 'payment_amount' => '1']);
        $check('a discount above the fees leaves nothing to collect, so the deposit is refused', $fees->invoice((int) $bilal['id'], $m1) === null);
        $service->ensureInvoice($bilal, $i, $m2, (int) $u['id']);
        $vi = $fees->invoice((int) $bilal['id'], $m2);
        $fees->voidInvoice((int) $vi['id'], $i, (int) $u['id'], 'QA void');
        $check('a voided invoice shows a notice and no deposit box', str_contains($v = $get('/admin/fees/collect?student_id=' . $bilal['id'] . '&month=' . $m2)->content(), 'is voided') && !str_contains($v, 'data-ek-deposit'));
        $check('an unknown student id falls back to the search card', str_contains($get('/admin/fees/collect?student_id=999999999&month=' . $m1)->content(), 'Choose how you want to collect fees today'));
        $check('a bad month is handled without error', $get('/admin/fees/collect?student_id=' . $ali['id'] . '&month=2019-13')->status() === 200);
        // ---- Generate Fees Invoice: eSkooly copy toggles + fine after due date ------------------
        $vpost = static fn(array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/fees/vouchers/pdf']));
        $vb = ['scope' => 'student', 'student_id' => $ali['id'], 'month' => '2019-05', 'copies' => 'single'];
        $vpage = $get('/admin/fees/vouchers');
        $check('invoice page shows eSkooly tabs, fine and copy toggles', $vpage->status() === 200 && str_contains($vpage->content(), 'Generate Fees Invoice') && str_contains($vpage->content(), 'name="fine_after_due"') && str_contains($vpage->content(), 'value="bank"') && str_contains($vpage->content(), 'Class Wise'));
        foreach ([['student'], ['bank'], ['student', 'institute'], ['bank', 'student', 'institute']] as $set) {
            $pdf = $vpost($vb + ['copy' => $set, 'fine_after_due' => '200']);
            $check('voucher PDF with ' . implode('+', $set) . ' copies renders', $pdf->status() === 200 && str_starts_with($pdf->content(), '%PDF-'));
        }
        $check('no copy selected is refused', $vpost($vb + ['copy' => ['nonsense']])->status() === 422);
        $check('a negative fine is refused', $vpost($vb + ['copy' => ['student'], 'fine_after_due' => '-1'])->status() === 422);
        $check('a text fine is refused', $vpost($vb + ['copy' => ['student'], 'fine_after_due' => 'abc'])->status() === 422);
        $check('an absurd fine is refused', $vpost($vb + ['copy' => ['student'], 'fine_after_due' => '99999999'])->status() === 422);
        $check('the old copies option still works without toggles', str_starts_with($vpost($vb + ['copies' => 'three_copy'])->content(), '%PDF-'));
        $check('class wise without a class is refused', str_contains($loc($vpost(['scope' => 'class', 'class' => 0, 'month' => '2019-05', 'copy' => ['student']])), '/admin/fees/vouchers'));
        $check('previewing a class lists its students', str_contains($get('/admin/fees/vouchers?scope=class&class=' . $class . '&month=2019-05')->content(), 'CQ-' . $suffix . '-1'));
        if (getenv('FEE_COLLECT_QA_DIR')) { file_put_contents(getenv('FEE_COLLECT_QA_DIR') . '/v3.pdf', $vpost(array_replace($vb, ['copy' => ['bank', 'student', 'institute'], 'fine_after_due' => '250', 'due_date' => '2019-05-10', 'month' => '2019-06']))->content()); }
        // ---- due date, extra charges, voucher settings, preview -----------------------------------
        $dueMonth = '2019-07';
        $service->ensureInvoice($ali, $i, $dueMonth, (int) $u['id']);
        $vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => $dueMonth, 'copy' => ['student'], 'due_date' => '2019-07-10']);
        $check('a due date entered on the voucher screen is saved on an existing invoice', $fees->invoice((int) $ali['id'], $dueMonth)['due_date'] === '2019-07-10');
        $vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => $dueMonth, 'copy' => ['student'], 'due_date' => '2019-07-15']);
        $check('a new due date replaces the old one', $fees->invoice((int) $ali['id'], $dueMonth)['due_date'] === '2019-07-15');
        $check('an invalid due date is refused', str_contains($loc($vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => $dueMonth, 'copy' => ['student'], 'due_date' => '2019-02-31'])), '/admin/fees/vouchers') && $fees->invoice((int) $ali['id'], $dueMonth)['due_date'] === '2019-07-15');

        $classMonth = '2019-08';
        $res = $vpost(['scope' => 'class', 'class' => $class, 'month' => $classMonth, 'copy' => ['student'], 'extra_label' => ['Exam Fee', 'Fine'], 'extra_amount' => ['800', '150']]);
        $classInv = array_map(fn($s) => $fees->invoice((int) $s['id'], $classMonth), [$ali, $sara]);
        $itemsOf = fn($inv) => array_column($fees->items((int) $inv['id']), 'amount', 'particular');
        $check('class-wide extra charges are added to every student invoice', $res->status() === 200 && count(array_filter($classInv, fn($inv) => $inv && (float) ($itemsOf($inv)['Exam Fee'] ?? 0) === 800.0 && (float) ($itemsOf($inv)['Fine'] ?? 0) === 150.0)) === 2);
        $before = array_map(fn($inv) => (float) $inv['total_amount'], $classInv);
        $vpost(['scope' => 'class', 'class' => $class, 'month' => $classMonth, 'copy' => ['student'], 'extra_label' => ['Exam Fee'], 'extra_amount' => ['1000']]);
        $again = array_map(fn($s) => $fees->invoice((int) $s['id'], $classMonth), [$ali, $sara]);
        $check('generating again with the same charge name updates it instead of adding it twice', abs((float) $again[0]['total_amount'] - ($before[0] + 200)) < 0.01 && count(array_filter(array_keys($itemsOf($again[0])), fn($k) => $k === 'Exam Fee')) === 1);
        $check('extra charges are audited', (bool) $db->query("SELECT 1 FROM audit_logs WHERE action='fee.extra_charges_added' ORDER BY id DESC LIMIT 1")->fetchColumn());
        $res = $vpost(['scope' => 'student', 'student_id' => $bilal['id'], 'month' => '2019-09', 'copy' => ['student'], 'extra_label' => ['Trip'], 'extra_amount' => ['2500']]);
        $check('a one-off charge can be added for a single student', (float) ($itemsOf($fees->invoice((int) $bilal['id'], '2019-09'))['Trip'] ?? 0) === 2500.0 && $fees->invoice((int) $sara['id'], '2019-09') === null);
        foreach (['charge name without amount' => [['Exam'], ['']], 'amount without name' => [[''], ['100']], 'zero amount' => [['Exam'], ['0']], 'negative amount' => [['Exam'], ['-5']], 'text amount' => [['Exam'], ['abc']], 'too-long name' => [[str_repeat('x', 61)], ['5']]] as $label => [$names, $amts]) {
            $check('extra charge with ' . $label . ' is refused', $vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => '2019-09', 'copy' => ['student'], 'extra_label' => $names, 'extra_amount' => $amts])->status() === 422 && $fees->invoice((int) $ali['id'], '2019-09') === null);
        }
        $check('more than 10 extra charges at once is refused', $vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => '2019-09', 'copy' => ['student'], 'extra_label' => array_map(fn($n) => 'C' . $n, range(1, 11)), 'extra_amount' => array_fill(0, 11, '1')])->status() === 422);

        // ---- extra charges show up in accounts -------------------------------------------------
        $aliAug = $fees->invoice((int) $ali['id'], $classMonth);
        $post(['registration' => $ali['registration_no'], 'month' => $classMonth, 'payment_method' => 'Cash', 'payment_amount' => '500', 'reference' => 'ACC-' . $suffix]);
        $acct = new \App\Repositories\AccountRepository($db);
        $today = date('Y-m-d');
        $ledgerRow = array_values(array_filter($acct->ledger($i, $today, $today, 'fee', 0, ''), fn($r) => $r['reference'] && str_contains((string) $r['description'], 'Collect Ali ' . $suffix) && str_contains((string) $r['description'], $classMonth)));
        $check('the fee payment row in the ledger lists the extra charges', $ledgerRow && str_contains((string) $ledgerRow[0]['items'], 'Exam Fee 1,000') && str_contains((string) $ledgerRow[0]['items'], 'Fine 150'));
        $check('searching the ledger for an extra charge finds the payment', (bool) array_filter($acct->ledger($i, $today, $today, '', 0, 'Exam Fee'), fn($r) => str_contains((string) $r['description'], 'Collect Ali ' . $suffix)));
        $stmt = $get('/admin/accounts/statement?from_date=' . $today . '&to_date=' . $today . '&source=fee&search=' . rawurlencode('Collect Ali ' . $suffix))->content();
        $check('account statement shows the extra charge under the fee payment', str_contains($stmt, 'Exam Fee 1,000') && str_contains($stmt, 'Fee Charges Billed'));
        $billed = array_column($acct->feeChargesBilled($i, $classMonth, $classMonth)['rows'], null, 'particular');
        $check('fee charges billed totals each extra charge across the class', (float) ($billed['Exam Fee']['billed'] ?? 0) === 3000.0 && (int) ($billed['Exam Fee']['invoices'] ?? 0) === 3 && (float) ($billed['Fine']['billed'] ?? 0) === 450.0);
        $tot = $acct->feeChargesBilled($i, $classMonth, $classMonth)['totals'];
        $check('fee charges billed shows collected and still-due totals', $tot['collected'] >= 500.0 && $tot['remaining'] > 0);
        $daily = $get('/admin/accounts/daily?date=' . $today)->content();
        $check('the daily report lists the extra charges on the payment', str_contains($daily, 'Exam Fee 1,000'));
        $rep = $get('/admin/reports/accounts?from=' . $classMonth . '-01&to=' . $today)->content();
        $check('the accounts report now includes fee collections and the billed charges', str_contains($rep, 'Fee Collections') && str_contains($rep, 'Fee Charges Billed') && str_contains($rep, 'Exam Fee'));

        // settings
        $settingsPage = $get('/admin/settings/fee-voucher');
        $check('fee voucher settings page renders with preview buttons', $settingsPage->status() === 200 && str_contains($settingsPage->content(), 'Preview test invoice') && str_contains($settingsPage->content(), 'voucher_instructions'));
        $check('fee voucher settings appear under General Settings', str_contains($settingsPage->content(), '/admin/settings/fee-voucher') && str_contains($settingsPage->content(), 'Fee Voucher Settings'));
        $vs = new \App\Services\FeeVoucherService($db);
        $oldSettings = $db->query('SELECT voucher_instructions,voucher_bank_ids,voucher_default_fine FROM fee_settings WHERE institute_id=:i', ['i' => $i])->fetch();
        $spost = static fn(string $uri, array $body) => $app->run(new Request([], $body + ['_token' => CSRF::token()], [], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => $uri]));
        try {
            $spost('/admin/settings/fee-voucher/bank', ['bank_name' => 'QA EasyPaisa ' . $suffix, 'account_number' => '0334-0000000', 'bank_address' => '', 'instructions' => '**Account Name:** QA']);
            $qaBank = (int) $db->query('SELECT id FROM fee_invoice_accounts WHERE institute_id=:i AND bank_name=:n', ['i' => $i, 'n' => 'QA EasyPaisa ' . $suffix])->fetchColumn();
            $check('a bank account can be added from voucher settings', $qaBank > 0);
            $spost('/admin/settings/fee-voucher/bank', ['bank_name' => '', 'account_number' => 'QA-' . $suffix]);
            $check('a bank account without a name is refused', (int) $db->query('SELECT COUNT(*) FROM fee_invoice_accounts WHERE institute_id=:i AND account_number=:a', ['i' => $i, 'a' => 'QA-' . $suffix])->fetchColumn() === 0);
            $spost('/admin/settings/fee-voucher', ['voucher_instructions' => "1. Pay **on time**.\n2. Keep the receipt.", 'voucher_default_fine' => '300', 'bank_ids' => [$qaBank, 99999999]]);
            $st = $vs->settings($i);
            $check('voucher instructions, default fine and chosen banks are saved', str_contains($st['instructions'], '**on time**') && $st['default_fine'] === 300.0 && $st['bank_ids'] === [$qaBank]);
            $check('only the chosen bank accounts print', array_map('intval', array_column($vs->banksFor($i), 'id')) === [$qaBank]);
            $check('the default fine is pre-filled on Generate Fees Invoice', str_contains($get('/admin/fees/vouchers')->content(), 'name="fine_after_due" id="voucher_fine" value="300"'));
            $spost('/admin/settings/fee-voucher', ['voucher_instructions' => 'x', 'voucher_default_fine' => '-1']);
            $check('a negative default fine is refused', $vs->settings($i)['default_fine'] === 300.0);
            $spost('/admin/settings/fee-voucher', ['voucher_instructions' => str_repeat('a', 3001), 'voucher_default_fine' => '0']);
            $check('over-long instructions are refused', str_contains($vs->settings($i)['instructions'], '**on time**'));
            $invoicesBefore = (int) $db->query('SELECT COUNT(*) FROM fee_invoices')->fetchColumn();
            foreach ([['three_copy', 'L'], ['student_institute', 'L'], ['single', 'L'], ['single', 'P']] as [$c, $o]) {
                $pv = $get('/admin/settings/fee-voucher/preview?copies=' . $c . '&orientation=' . $o);
                $check("test invoice preview renders ($c, $o)", $pv->status() === 200 && str_starts_with($pv->content(), '%PDF-'));
            }
            $check('previewing a test invoice writes nothing to the database', (int) $db->query('SELECT COUNT(*) FROM fee_invoices')->fetchColumn() === $invoicesBefore);
            $pdf = $vpost(['scope' => 'student', 'student_id' => $ali['id'], 'month' => $dueMonth, 'copy' => ['institute'], 'orientation' => 'P']);
            $check('a single institute copy can be generated in portrait', $pdf->status() === 200 && str_starts_with($pdf->content(), '%PDF-'));
            $one = $get('/admin/reports/fee-invoice/' . $fees->invoice((int) $ali['id'], $dueMonth)['id'] . '/pdf');
            $check('the Fee Voucher button on Collect Fees uses the same voucher layout', $one->status() === 200 && str_starts_with($one->content(), '%PDF-'));
        } finally {
            $db->query('UPDATE fee_settings SET voucher_instructions=:a,voucher_bank_ids=:b,voucher_default_fine=:c WHERE institute_id=:i', ['a' => $oldSettings['voucher_instructions'] ?? null, 'b' => $oldSettings['voucher_bank_ids'] ?? null, 'c' => $oldSettings['voucher_default_fine'] ?? 0, 'i' => $i]);
            $db->query('DELETE FROM fee_invoice_accounts WHERE institute_id=:i AND bank_name=:n', ['i' => $i, 'n' => 'QA EasyPaisa ' . $suffix]);
        }
    } finally {
        foreach ($ids as $id) {
            $db->query('DELETE FROM fee_payments WHERE student_id=:id', ['id' => $id]);
            $db->query('DELETE fii FROM fee_invoice_items fii JOIN fee_invoices fi ON fi.id=fii.fee_invoice_id WHERE fi.student_id=:id', ['id' => $id]);
            $db->query('DELETE FROM fee_invoices WHERE student_id=:id', ['id' => $id]);
            $db->query('DELETE FROM students WHERE id=:id', ['id' => $id]);
        }
        $db->query('DELETE FROM classes WHERE id=:id', ['id' => $class]);
    }
    return $out;
};
