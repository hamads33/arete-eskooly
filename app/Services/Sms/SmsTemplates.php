<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/** SMS template storage. Default templates are created per institute on first use. */
final class SmsTemplates
{
    public const CATEGORIES = ['attendance' => 'Attendance', 'fees' => 'Fees', 'exams' => 'Exams', 'results' => 'Results',
        'announcements' => 'Announcements', 'admissions' => 'Admissions', 'general' => 'General'];

    /** slug => [name, category, body]; referenced by the default automation rules. */
    public const DEFAULTS = [
        'attendance-absent' => ['Attendance Absence Alert', 'attendance', 'Dear {{parent_name}}, {{student_name}} ({{class_name}}) was marked {{attendance_status}} on {{date}}. - {{school_name}}'],
        'fee-due' => ['Fee Due Reminder', 'fees', 'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) is due on {{due_date}}. - {{school_name}}'],
        'fee-overdue' => ['Fee Overdue Reminder', 'fees', 'Dear {{parent_name}}, fee of {{fee_amount}} for {{student_name}} ({{fee_month}}) was due on {{due_date}} and is still unpaid. Please clear it at the earliest. - {{school_name}}'],
        'fee-payment' => ['Payment Confirmation', 'fees', 'Dear {{parent_name}}, we received {{amount_paid}} for {{student_name}} (receipt {{receipt_no}}). Thank you. - {{school_name}}'],
        'result-published' => ['Result Published', 'results', 'Dear {{parent_name}}, {{student_name}}\'s result for {{exam_name}} has been published. Percentage: {{percentage}}%. - {{school_name}}'],
        'admission-confirmed' => ['Admission Confirmed', 'admissions', 'Dear {{parent_name}}, admission of {{student_name}} in {{class_name}} is confirmed. Registration no: {{registration_no}}. - {{school_name}}'],
        'announcement' => ['Announcement', 'announcements', '{{announcement_title}} - {{school_name}}'],
    ];

    public function __construct(private readonly Database $db) {}

    public function ensureDefaults(int $institute): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->connection()->prepare('INSERT IGNORE INTO sms_templates(institute_id,name,slug,category,message_body,variables_json,is_active,is_system,created_at,updated_at) VALUES(?,?,?,?,?,?,1,1,?,?)');
        foreach (self::DEFAULTS as $slug => [$name, $category, $body]) {
            $stmt->execute([$institute, $name, $slug, $category, $body, json_encode(SmsTemplateRenderer::placeholders($body)), $now, $now]);
        }
    }

    public function all(int $institute, bool $activeOnly = false): array
    {
        return $this->db->query('SELECT * FROM sms_templates WHERE institute_id=:i' . ($activeOnly ? ' AND is_active=1' : '') . ' ORDER BY category,name', ['i' => $institute])->fetchAll();
    }

    public function find(int $institute, int $id): ?array
    {
        $row = $this->db->query('SELECT * FROM sms_templates WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute])->fetch();
        return $row ?: null;
    }

    public function bySlug(int $institute, string $slug): ?array
    {
        $row = $this->db->query('SELECT * FROM sms_templates WHERE slug=:s AND institute_id=:i', ['s' => $slug, 'i' => $institute])->fetch();
        return $row ?: null;
    }

    public function save(int $institute, ?int $id, array $d, int $user): int
    {
        $name = trim((string) ($d['name'] ?? ''));
        $body = trim((string) ($d['message_body'] ?? ''));
        $category = (string) ($d['category'] ?? 'general');
        if ($name === '' || mb_strlen($name) > 120) {
            throw new DomainException('Template name is required (up to 120 characters).');
        }
        if ($body === '' || mb_strlen($body) > SmsText::MAX_LENGTH) {
            throw new DomainException('Message body is required (up to ' . SmsText::MAX_LENGTH . ' characters).');
        }
        if (!isset(self::CATEGORIES[$category])) {
            throw new DomainException('Select a valid category.');
        }
        SmsTemplateRenderer::validate($body);
        if ($id) {
            foreach ($this->db->query('SELECT event_type FROM sms_automation_rules WHERE institute_id=:i AND template_id=:t', ['i' => $institute, 't' => $id])->fetchAll(\PDO::FETCH_COLUMN) as $event) {
                if (isset(SmsAutomation::EVENTS[$event])) SmsAutomation::assertTemplateFits($event, $body);
            }
        }
        $vars = json_encode(SmsTemplateRenderer::placeholders($body));
        $now = date('Y-m-d H:i:s');
        $active = !empty($d['is_active']) ? 1 : 0;
        if ($id) {
            if (!$this->find($institute, $id)) {
                throw new DomainException('Template not found.');
            }
            $this->db->query('UPDATE sms_templates SET name=:n,category=:c,message_body=:b,variables_json=:v,is_active=:a,updated_at=:u WHERE id=:id AND institute_id=:i',
                ['n' => $name, 'c' => $category, 'b' => $body, 'v' => $vars, 'a' => $active, 'u' => $now, 'id' => $id, 'i' => $institute]);
            return $id;
        }
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'template';
        $slug = $base;
        for ($n = 2; $this->bySlug($institute, $slug); $n++) {
            $slug = $base . '-' . $n;
        }
        $this->db->query('INSERT INTO sms_templates(institute_id,name,slug,category,message_body,variables_json,is_active,is_system,created_by,created_at,updated_at) VALUES(:i,:n,:s,:c,:b,:v,:a,0,:by,:t,:t2)',
            ['i' => $institute, 'n' => $name, 's' => $slug, 'c' => $category, 'b' => $body, 'v' => $vars, 'a' => $active, 'by' => $user, 't' => $now, 't2' => $now]);
        return (int) $this->db->connection()->lastInsertId();
    }

    /** System templates back the automations and can be edited or deactivated, never deleted. */
    public function delete(int $institute, int $id): void
    {
        $t = $this->find($institute, $id);
        if (!$t) {
            throw new DomainException('Template not found.');
        }
        if ((int) $t['is_system'] === 1) {
            throw new DomainException('Built-in templates are used by automations; deactivate or edit them instead.');
        }
        $this->db->query('DELETE FROM sms_templates WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $institute]);
    }
}
