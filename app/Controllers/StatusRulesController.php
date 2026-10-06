<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request, Response};
use App\Repositories\SettingsRepository;
use App\Support\{Flash, PersonStatus};

/** General Settings → Inactive & Suspended Rules: what an inactive or suspended student / employee still appears in. */
final class StatusRulesController extends AdminPageController
{
    private function institute(): int { return (int) (new SettingsRepository($this->app->database()))->institute()['id']; }

    public function index(Request $r): Response
    {
        $db = $this->app->database();
        $i = $this->institute();
        $counts = [];
        foreach (['student' => 'students', 'employee' => 'employees'] as $type => $table) {
            foreach ($db->query("SELECT status,COUNT(*) n FROM {$table} WHERE institute_id=:i GROUP BY status", ['i' => $i])->fetchAll() as $row) $counts[$type][$row['status']] = (int) $row['n'];
        }
        $recent = $db->query("SELECT h.*,COALESCE(s.student_name,e.name) person_name,COALESCE(s.registration_no,e.employee_no) person_no,u.name changed_by_name FROM person_status_history h LEFT JOIN students s ON h.person_type='student' AND s.id=h.person_id LEFT JOIN employees e ON h.person_type='employee' AND e.id=h.person_id LEFT JOIN users u ON u.id=h.changed_by WHERE h.institute_id=:i ORDER BY h.created_at DESC,h.id DESC LIMIT 25", ['i' => $i])->fetchAll();
        return $this->renderPage('status-rules', ['rules' => PersonStatus::rules($db, $i), 'counts' => $counts, 'recent' => $recent]);
    }

    public function save(Request $r): Response
    {
        if (!can('*')) return Response::html('Only an administrator can change these rules.', 403);
        PersonStatus::saveRules($this->app->database(), $this->institute(), (array) $r->input('rules', []), (int) user()['id']);
        (new \App\Repositories\PakistanErpRepository($this->app->database()))->audit($this->institute(), (int) user()['id'], 'status.rules_updated', 'person_status_rules', null, ['rules' => (array) $r->input('rules', [])]);
        Flash::add('success', 'Rules for inactive and suspended students and employees saved.');
        return Response::redirect(url('/admin/settings/status-rules'));
    }
}
