<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;

/**
 * Event-driven SMS: modules report what happened, rules decide whether a message is queued.
 * No message text lives in the attendance/fee/exam code; it comes from the rule's template.
 *
 * Every event has a fixed dedupe key, so saving attendance again, re-running the reminder
 * script or re-publishing a result never queues a second copy.
 *
 * Handlers never throw into the calling module: an SMS problem must not stop attendance from
 * saving or a payment from being recorded.
 */
final class SmsAutomation
{
    /** event => [label, trigger description, default template slug, settings toggle, default conditions] */
    public const EVENTS = [
        'attendance_absent' => ['Attendance Alert', 'A student is marked absent (today\'s attendance only)', 'attendance-absent', 'attendance_sms_enabled', ['statuses' => ['Absent']]],
        'fee_due' => ['Fee Due Reminder', 'An unpaid invoice falls due within the next few days', 'fee-due', 'fee_sms_enabled', ['days_before' => 3]],
        'fee_overdue' => ['Fee Overdue Reminder', 'An invoice is still unpaid after its due date', 'fee-overdue', 'fee_sms_enabled', ['days_after' => 1]],
        'fee_payment' => ['Payment Confirmation', 'A fee payment is recorded', 'fee-payment', 'fee_sms_enabled', []],
        'result_published' => ['Result Published', 'An exam is published, or online test results are published', 'result-published', 'result_sms_enabled', []],
        'admission_confirmed' => ['Admission Confirmed', 'A new student is admitted', 'admission-confirmed', null, []],
    ];

    /** Variables every parent automation can fill (from the student, parent and institute). */
    private const BASE_VARIABLES = ['school_name', 'school_phone', 'recipient_name', 'student_name', 'parent_name', 'class_name', 'registration_no', 'date'];
    /** Extra variables each event fills. A template may only use BASE_VARIABLES + these. */
    public const EVENT_VARIABLES = [
        'attendance_absent' => ['attendance_status'],
        'fee_due' => ['fee_month', 'fee_amount', 'due_date', 'invoice_no'],
        'fee_overdue' => ['fee_month', 'fee_amount', 'due_date', 'invoice_no'],
        'fee_payment' => ['amount_paid', 'receipt_no', 'fee_month', 'invoice_no', 'fee_amount'],
        'result_published' => ['exam_name', 'marks', 'percentage'],
        'admission_confirmed' => [],
    ];

    /** Why the most recent automation could not queue its SMS (shown to the admin, e.g. after collecting a fee). */
    public static array $lastSkipped = [];

    private SmsDispatcher $sms;

    /** @return list<string> variables a template used by $event can contain */
    public static function variablesFor(string $event): array
    {
        return array_values(array_unique(array_merge(self::BASE_VARIABLES, self::EVENT_VARIABLES[$event] ?? [])));
    }

    /** @throws \DomainException when $body uses a variable the $event automation can never fill */
    public static function assertTemplateFits(string $event, string $body): void
    {
        $missing = array_values(array_diff(SmsTemplateRenderer::placeholders($body), self::variablesFor($event)));
        if ($missing) {
            throw new \DomainException('The ' . self::EVENTS[$event][0] . ' SMS cannot fill {{' . implode('}}, {{', $missing) . '}}, so it would never be sent. Variables you can use: {{' . implode('}}, {{', self::variablesFor($event)) . '}}.');
        }
    }

    public function __construct(private readonly Database $db)
    {
        $this->sms = new SmsDispatcher($db);
    }

    public function ensureDefaults(int $institute): void
    {
        $this->sms->templates->ensureDefaults($institute);
        $now = date('Y-m-d H:i:s');
        foreach (self::EVENTS as $event => [, , $slug, , $conditions]) {
            $template = $this->sms->templates->bySlug($institute, $slug);
            $this->db->query("INSERT IGNORE INTO sms_automation_rules(institute_id,event_type,template_id,enabled,recipient_target,conditions_json,created_at,updated_at) VALUES(:i,:e,:t,0,'guardian',:c,:n,:n2)",
                ['i' => $institute, 'e' => $event, 't' => $template['id'] ?? null, 'c' => json_encode($conditions), 'n' => $now, 'n2' => $now]);
        }
    }

    public function rules(int $institute): array
    {
        $this->ensureDefaults($institute);
        $rows = $this->db->query('SELECT r.*,t.name template_name,t.is_active template_active FROM sms_automation_rules r LEFT JOIN sms_templates t ON t.id=r.template_id WHERE r.institute_id=:i', ['i' => $institute])->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            if (isset(self::EVENTS[$r['event_type']])) {
                $r['conditions'] = (json_decode((string) $r['conditions_json'], true) ?: []) + self::EVENTS[$r['event_type']][4];
                $out[$r['event_type']] = $r;
            }
        }
        $ordered = [];
        foreach (array_keys(self::EVENTS) as $event) {
            if (isset($out[$event])) {
                $ordered[$event] = $out[$event];
            }
        }
        return $ordered;
    }

    public function saveRule(int $institute, string $event, array $input, int $user): void
    {
        if (!isset(self::EVENTS[$event])) {
            throw new \DomainException('Unknown automation.');
        }
        $this->ensureDefaults($institute);
        $template = (int) ($input['template_id'] ?? 0);
        $found = $this->sms->templates->find($institute, $template);
        if (!$found) {
            throw new \DomainException('Select a template for this automation.');
        }
        self::assertTemplateFits($event, (string) $found['message_body']);
        $conditions = self::EVENTS[$event][4];
        if ($event === 'attendance_absent') {
            $statuses = array_values(array_intersect((array) ($input['statuses'] ?? []), ['Absent', 'Late', 'Leave']));
            $conditions['statuses'] = $statuses ?: ['Absent'];
        }
        if ($event === 'fee_due') {
            $conditions['days_before'] = max(0, min(30, (int) ($input['days_before'] ?? 3)));
        }
        if ($event === 'fee_overdue') {
            $conditions['days_after'] = max(1, min(60, (int) ($input['days_after'] ?? 1)));
        }
        $enabled = !empty($input['enabled']) ? 1 : 0;
        $this->db->query('UPDATE sms_automation_rules SET template_id=:t,enabled=:e,conditions_json=:c,created_by=COALESCE(created_by,:u),updated_at=:n WHERE institute_id=:i AND event_type=:ev',
            ['t' => $template, 'e' => $enabled, 'c' => json_encode($conditions), 'u' => $user, 'n' => date('Y-m-d H:i:s'), 'i' => $institute, 'ev' => $event]);
        (new SmsLogger($this->db))->log($institute, 'automation_updated', SmsLogger::user(), null, null, ['event' => $event, 'enabled' => (bool) $enabled]);
    }

    /** The rule and its template when the automation should fire, otherwise null. */
    private function active(int $institute, string $event): ?array
    {
        $s = $this->sms->settings->get($institute);
        $toggle = self::EVENTS[$event][3];
        if (!$s['sms_enabled'] || ($toggle && !$s[$toggle])) {
            return null;
        }
        $rule = $this->db->query('SELECT r.*,t.message_body,t.is_active FROM sms_automation_rules r JOIN sms_templates t ON t.id=r.template_id WHERE r.institute_id=:i AND r.event_type=:e AND r.enabled=1',
            ['i' => $institute, 'e' => $event])->fetch();
        if (!$rule || !(int) $rule['is_active']) {
            return null;
        }
        $rule['conditions'] = (json_decode((string) $rule['conditions_json'], true) ?: []) + self::EVENTS[$event][4];
        return $rule;
    }

    /**
     * Queues one message per parent entry; entries without a usable number or a value for every
     * template variable are skipped (and logged) rather than sent incomplete.
     * @return int messages newly queued
     */
    private function queueFor(int $institute, array $rule, array $entries, string $type, string $source, callable $key): int
    {
        $made = 0;
        $log = new SmsLogger($this->db);
        foreach ($entries as $e) {
            if ($e['phone'] === null) {
                self::$lastSkipped[] = $e['name'] . ': ' . $e['skip_reason'];
                $log->log($institute, 'automation_skipped', SmsLogger::system(), null, null, ['event' => $rule['event_type'], 'recipient' => $e['name'], 'reason' => $e['skip_reason']]);
                continue;
            }
            try {
                $message = SmsTemplateRenderer::render((string) $rule['message_body'], $e['vars']);
                $r = $this->sms->queue->enqueue($institute, ['phone_number' => $e['phone'], 'message' => $message, 'recipient_type' => $e['recipient_type'], 'recipient_id' => $e['recipient_id'],
                    'recipient_name' => $e['name'], 'message_type' => $type, 'source' => $source, 'template_id' => (int) $rule['template_id'], 'dedupe_key' => $key($e), 'revive' => true], SmsLogger::system());
                $made += (int) $r['created'];
            } catch (\DomainException $ex) {
                self::$lastSkipped[] = $e['name'] . ': ' . $ex->getMessage();
                $log->log($institute, 'automation_skipped', SmsLogger::system(), null, null, ['event' => $rule['event_type'], 'recipient' => $e['name'], 'reason' => $ex->getMessage()]);
            }
        }
        return $made;
    }

    private function guard(callable $fn): int
    {
        try {
            return (int) $fn();
        } catch (\Throwable $e) {
            error_log('SMS automation skipped: ' . $e::class . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * After student attendance is saved. Only today's register triggers alerts, so back-filling
     * old dates never messages parents. A student corrected from Absent before the alert went out
     * has that alert cancelled; marking them absent again revives it.
     * @param array<int,string> $statuses student_id => status
     */
    public function attendanceSaved(string $date, array $statuses): int
    {
        return $this->guard(function () use ($date, $statuses) {
            if ($date !== date('Y-m-d') || !$statuses) {
                return 0;
            }
            $ids = array_map('intval', array_keys($statuses));
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->connection()->prepare("SELECT id,institute_id FROM students WHERE id IN ($marks)");
            $stmt->execute($ids);
            $byInstitute = [];
            foreach ($stmt->fetchAll() as $s) {
                $byInstitute[(int) $s['institute_id']][] = (int) $s['id'];
            }
            $made = 0;
            foreach ($byInstitute as $institute => $students) {
                $rule = $this->active($institute, 'attendance_absent');
                $alert = $rule ? $rule['conditions']['statuses'] : [];
                $notify = [];
                foreach ($students as $sid) {
                    $key = "attendance:{$sid}:{$date}";
                    if (in_array($statuses[$sid], $alert, true)) {
                        $notify[$sid] = ['date' => date('d M Y', strtotime($date)), 'attendance_status' => $statuses[$sid]];
                    } else {
                        // Also runs when the rule is off, so a correction still stops an earlier alert.
                        $this->sms->queue->cancelByKey($institute, $key, 'attendance corrected');
                    }
                }
                if ($rule && $notify) {
                    $made += $this->queueFor($institute, $rule, $this->sms->recipients->parentsOf($institute, array_keys($notify), $notify), 'attendance', 'attendance_module',
                        static fn($e) => "attendance:{$e['recipient_id']}:{$date}");
                }
            }
            return $made;
        });
    }

    /**
     * Queues the "fee received" SMS for a payment (also used by the receipt's "Send SMS" button when the
     * SMS never went out, e.g. the number was missing at the time).
     */
    public function paymentReceived(int $paymentId): int
    {
        self::$lastSkipped = [];
        return $this->guard(function () use ($paymentId) {
            $p = $this->db->query('SELECT p.id,p.amount,p.receipt_no,p.payment_date,p.student_id,s.institute_id,fi.fee_month,fi.invoice_no,fi.remaining_amount
                FROM fee_payments p JOIN students s ON s.id=p.student_id LEFT JOIN fee_invoices fi ON fi.id=p.fee_invoice_id WHERE p.id=:id AND p.reversed_at IS NULL', ['id' => $paymentId])->fetch();
            if (!$p || !($rule = $this->active((int) $p['institute_id'], 'fee_payment'))) {
                return 0;
            }
            $institute = (int) $p['institute_id'];
            $vars = [(int) $p['student_id'] => array_filter([
                'amount_paid' => $this->sms->recipients->money($institute, (float) $p['amount']),
                'receipt_no' => $p['receipt_no'],
                'fee_month' => $p['fee_month'] ? date('F Y', strtotime($p['fee_month'] . '-01')) : null,
                'invoice_no' => $p['invoice_no'],
                'fee_amount' => $p['fee_month'] !== null ? $this->sms->recipients->money($institute, (float) $p['remaining_amount']) : null,
                'date' => $p['payment_date'] ? date('d M Y', strtotime($p['payment_date'])) : null,
            ], static fn($v) => $v !== null && $v !== '')];
            return $this->queueFor($institute, $rule, $this->sms->recipients->parentsOf($institute, [(int) $p['student_id']], $vars), 'fee', 'fee_module',
                static fn($e) => "payment:{$paymentId}");
        });
    }

    /** Whether the "fee received" automation is switched on (for showing the receipt's Send SMS button). */
    public function paymentSmsEnabled(int $institute): bool
    {
        return (bool) $this->active($institute, 'fee_payment');
    }

    public function resultPublished(int $institute, int $examId): int
    {
        return $this->guard(function () use ($institute, $examId) {
            if (!($rule = $this->active($institute, 'result_published'))) {
                return 0;
            }
            $entries = $this->sms->recipients->group($institute, 'parents', ['filter' => 'exam', 'exam_id' => $examId]);
            return $this->queueFor($institute, $rule, $entries, 'result', 'exam_module', static fn($e) => "result:{$examId}:{$e['recipient_id']}");
        });
    }

    /**
     * Online test results that were just released: one SMS home per attempt, whoever the result
     * is visible to in the portal. Keyed per attempt, so publishing the test again (or widening who
     * can see it) never sends a second copy.
     * @param list<int> $attemptIds
     */
    public function onlineResultsPublished(int $institute, array $attemptIds): int
    {
        self::$lastSkipped = [];
        return $this->guard(function () use ($institute, $attemptIds) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $attemptIds))));
            if (!$ids || !($rule = $this->active($institute, 'result_published'))) {
                return 0;
            }
            $rows = $this->db->query('SELECT a.id,a.student_id,a.total_score,t.title,(SELECT SUM(q.marks) FROM online_test_questions q WHERE q.test_id=t.id) total_marks
                FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id
                WHERE t.institute_id=? AND a.status=\'marked\' AND a.published_at IS NOT NULL AND a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$institute], $ids))->fetchAll();
            $num = static fn(float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
            $made = 0;
            foreach ($rows as $a) {
                $score = (float) $a['total_score'];
                $total = (float) $a['total_marks'];
                $vars = [(int) $a['student_id'] => ['exam_name' => $a['title'], 'marks' => $num($score) . '/' . $num($total), 'percentage' => (string) ($total > 0 ? round($score / $total * 100, 1) : 0)]];
                $made += $this->queueFor($institute, $rule, $this->sms->recipients->parentsOf($institute, [(int) $a['student_id']], $vars), 'result', 'online_test_module',
                    static fn($e) => 'online_result:' . $a['id']);
            }
            return $made;
        });
    }

    /** A withdrawn online test result: its SMS is cancelled if the gateway has not sent it yet. */
    public function onlineResultWithdrawn(int $institute, int $attemptId): bool
    {
        try {
            return $this->sms->queue->cancelByKey($institute, 'online_result:' . $attemptId, 'result withdrawn');
        } catch (\Throwable $e) {
            error_log('SMS automation skipped: ' . $e::class . ': ' . $e->getMessage());
            return false;
        }
    }

    public function admissionConfirmed(int $institute, int $studentId): int
    {
        return $this->guard(function () use ($institute, $studentId) {
            if (!($rule = $this->active($institute, 'admission_confirmed'))) {
                return 0;
            }
            return $this->queueFor($institute, $rule, $this->sms->recipients->parentsOf($institute, [$studentId]), 'admission', 'admission_module',
                static fn($e) => "admission:{$studentId}");
        });
    }

    /**
     * "Also send via SMS" on an announcement: one SMS per recipient in the chosen group, using the
     * admin's SMS text or the Announcement template. Keyed per announcement and phone, so a
     * double-submitted form cannot queue twice. A future publish time schedules the SMS for then.
     * Unlike automations this is a manual action, so problems are reported to the admin.
     * @return array{queued:int,skipped:int}
     */
    public function announcement(int $institute, int $announcementId, string $title, string $audience, string $smsText, ?int $classId, ?string $publishAt, int $user): array
    {
        if (!$this->sms->settings->get($institute)['announcement_sms_enabled']) {
            throw new \DomainException('Announcement SMS is turned off in SMS Settings.');
        }
        $body = trim($smsText);
        if ($body === '') {
            $template = $this->sms->templates->bySlug($institute, 'announcement');
            // The title is data, so any braces in it must not be read as placeholders.
            $body = str_replace('{{announcement_title}}', str_replace(['{', '}'], ['(', ')'], trim($title)), (string) ($template['message_body'] ?? '{{announcement_title}} - {{school_name}}'));
        }
        $scheduled = $publishAt && strtotime($publishAt) > time() + 60 ? $publishAt : null;
        $filters = $classId && $audience !== 'staff' ? ['filter' => 'class', 'class_id' => $classId] : ['filter' => 'all'];
        $r = $this->sms->bulkQueue($institute, $audience, $filters, $body, ['confirmed' => true, 'scheduled_at' => $scheduled, 'dedupe_prefix' => 'announcement:' . $announcementId,
            'template_id' => $smsText === '' ? ($this->sms->templates->bySlug($institute, 'announcement')['id'] ?? null) : null], $user, 'announcement', 'announcement_module');
        return ['queued' => $r['queued'], 'skipped' => $r['skipped']];
    }

    /**
     * Daily fee reminders, run by scripts/sms_daily.php. Due reminders cover the whole window up
     * to N days ahead so a missed run does not skip anyone; each invoice is reminded once per kind.
     */
    public function feeReminders(int $institute): int
    {
        return $this->guard(function () use ($institute) {
            $made = 0;
            $today = date('Y-m-d');
            foreach (['fee_due', 'fee_overdue'] as $event) {
                if (!($rule = $this->active($institute, $event))) {
                    continue;
                }
                if ($event === 'fee_due') {
                    $range = [$today, date('Y-m-d', strtotime('+' . (int) $rule['conditions']['days_before'] . ' days'))];
                } else {
                    $range = [date('Y-m-d', strtotime('-60 days')), date('Y-m-d', strtotime('-' . (int) $rule['conditions']['days_after'] . ' days'))];
                }
                $rows = $this->db->query("SELECT fi.id,fi.student_id,fi.fee_month,fi.remaining_amount,fi.due_date,fi.invoice_no FROM fee_invoices fi JOIN students s ON s.id=fi.student_id
                    WHERE s.institute_id=:i AND fi.voided_at IS NULL AND fi.status IN('unpaid','partial') AND fi.remaining_amount>0 AND fi.due_date BETWEEN :a AND :b",
                    ['i' => $institute, 'a' => $range[0], 'b' => $range[1]])->fetchAll();
                foreach ($rows as $inv) {
                    $vars = [(int) $inv['student_id'] => ['fee_amount' => $this->sms->recipients->money($institute, (float) $inv['remaining_amount']),
                        'due_date' => date('d M Y', strtotime($inv['due_date'])), 'fee_month' => date('F Y', strtotime($inv['fee_month'] . '-01')), 'invoice_no' => $inv['invoice_no']]];
                    $made += $this->queueFor($institute, $rule, $this->sms->recipients->parentsOf($institute, [(int) $inv['student_id']], $vars), 'fee', 'fee_module',
                        static fn($e) => "{$event}:{$inv['id']}");
                }
            }
            return $made;
        });
    }
}
