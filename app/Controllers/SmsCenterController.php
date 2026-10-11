<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Request,Response};
use App\Repositories\SettingsRepository;
use App\Services\Sms\{SmsAutomation,SmsDispatcher,SmsGateway,SmsLogger,SmsQueue,SmsRecipients,SmsTemplateRenderer,SmsTemplates,SmsText};
use App\Support\Flash;
use DomainException;

/**
 * SMS Center (admin). The module gate in config/permissions.php admits holders of sms.view;
 * every action below additionally checks its own sms.* permission.
 *
 * "Send" always means "queue for the SMS gateway": nothing is reported as sent until an
 * Android gateway confirms it through the API.
 */
final class SmsCenterController extends AdminPageController
{
    private ?SmsDispatcher $sms = null;

    private function i(): int
    {
        return (int) (new SettingsRepository($this->app->database()))->institute()['id'];
    }

    private function sms(): SmsDispatcher
    {
        return $this->sms ??= new SmsDispatcher($this->app->database());
    }

    private function gateway(): SmsGateway
    {
        return new SmsGateway($this->app->database(), $this->sms()->settings);
    }

    private function deny(string $permission): ?Response
    {
        if (can($permission)) {
            return null;
        }
        return Response::html(\App\Core\View::render('errors/403', ['pageTitle' => 'Access Denied'], 'layouts/error'), 403);
    }

    private function page(string $view, string $title, string $sub, array $data = []): Response
    {
        (new SmsAutomation($this->app->database()))->ensureDefaults($this->i());
        return $this->view('sms/' . $view, array_replace([
            'pageTitle' => $title, 'activeMenu' => 'sms', 'activeSubmenu' => $sub,
            'institute' => (new SettingsRepository($this->app->database()))->institute(),
            'smsSettings' => $this->sms()->settings->get($this->i()),
            'styles' => ['css/sms-center.css?v=3'], 'scripts' => ['js/sms-center.js?v=2'],
        ], $data));
    }

    private function back(string $path, string $type, string $message): Response
    {
        Flash::add($type, $message);
        return Response::redirect(url($path));
    }

    /** Only same-module return targets are honoured, so a crafted "return" cannot redirect off-site. */
    private function returnTo(Request $r, string $default): string
    {
        $to = (string) $r->input('return', '');
        return preg_match('#^/admin/sms(/[a-z\-/0-9]*)?(\?[A-Za-z0-9_=&%\-.]*)?$#', $to) ? $to : $default;
    }

    // ---- dashboard -----------------------------------------------------------------------

    public function dashboard(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        $i = $this->i();
        $devices = $this->gateway()->all($i);
        $lastActivity = null;
        foreach ($devices as $d) {
            if ($d['last_seen_at'] && ($lastActivity === null || $d['last_seen_at'] > $lastActivity)) {
                $lastActivity = $d['last_seen_at'];
            }
        }
        return $this->page('dashboard', 'SMS Center', 'sms-dashboard', [
            'stats' => $this->sms()->queue->stats($i),
            'usage' => $this->sms()->queue->usage($i),
            'recent' => $this->sms()->queue->search($i, [], 1, 10)['rows'],
            'connected' => count(array_filter($devices, static fn($d) => $d['live_status'] === 'connected')),
            'deviceCount' => count($devices),
            'lastActivity' => $lastActivity,
        ]);
    }

    // ---- compose -------------------------------------------------------------------------

    public function compose(Request $r): Response
    {
        if ($x = $this->deny('sms.compose')) return $x;
        return $this->page('compose', 'Compose SMS', 'sms-compose', [
            'templates' => $this->sms()->templates->all($this->i(), true),
            'variables' => SmsTemplateRenderer::VARIABLES,
            'old' => (array) ($this->app->session()->pull('sms_old') ?? []),
        ]);
    }

    public function storeCompose(Request $r): Response
    {
        if ($x = $this->deny('sms.compose')) return $x;
        $input = ['recipient_type' => (string) $r->input('recipient_type'), 'recipient_id' => (int) $r->input('recipient_id'), 'recipient_name' => (string) $r->input('recipient_name'),
            'phone_number' => (string) $r->input('phone_number'), 'message' => (string) $r->input('message'), 'template_id' => (int) $r->input('template_id'),
            'priority' => (string) $r->input('priority'), 'scheduled_at' => $r->input('send_mode') === 'schedule' ? (string) $r->input('scheduled_at') : null];
        try {
            if ($r->input('send_mode') === 'schedule' && trim((string) $input['scheduled_at']) === '') {
                throw new DomainException('Choose the date and time to send this SMS.');
            }
            $id = $this->sms()->compose($this->i(), $input, (int) user()['id']);
        } catch (DomainException $e) {
            $this->app->session()->flash('sms_old', $input + ['recipient_label' => (string) $r->input('recipient_label'), 'send_mode' => (string) $r->input('send_mode')]);
            return $this->back('/admin/sms/compose', 'error', $e->getMessage());
        }
        return $this->back('/admin/sms/messages/' . $id, 'success', $input['scheduled_at'] ? 'SMS scheduled. It will be queued for the SMS gateway at the chosen time.' : 'SMS queued for the SMS gateway. It will be sent when a gateway device picks it up.');
    }

    /** Autocomplete for the compose page: JSON, institute-scoped, minimal fields. */
    public function recipients(Request $r): Response
    {
        if ($x = $this->deny('sms.compose')) return Response::json(['results' => []], 403);
        $type = (string) $r->query('type', 'student');
        $q = trim((string) $r->query('q', ''));
        if (!in_array($type, ['student', 'guardian', 'staff'], true) || mb_strlen($q) < 2) {
            return Response::json(['results' => []]);
        }
        return Response::json(['results' => $this->sms()->recipients->search($this->i(), $type, $q)]);
    }

    // ---- bulk ----------------------------------------------------------------------------

    private function bulkInput(Request $r): array
    {
        return ['audience' => (string) $r->input('audience', 'parents'), 'filter' => (string) $r->input('filter', 'all'), 'class_id' => (int) $r->input('class_id'),
            'section_id' => (int) $r->input('section_id'), 'date' => (string) $r->input('date', date('Y-m-d')), 'attendance_status' => (string) $r->input('attendance_status', 'Absent'),
            'exam_id' => (int) $r->input('exam_id'), 'template_id' => (int) $r->input('template_id'), 'message' => (string) $r->input('message'),
            'priority' => (string) $r->input('priority'), 'send_mode' => (string) $r->input('send_mode', 'now'), 'scheduled_at' => (string) $r->input('scheduled_at')];
    }

    public function bulk(Request $r): Response
    {
        if ($x = $this->deny('sms.bulk_send')) return $x;
        $i = $this->i();
        $input = $this->bulkInput($r);
        $preview = null;
        $error = null;
        if ($r->query('preview')) {
            try {
                if (trim($input['message']) === '') {
                    throw new DomainException('Write the message (or pick a template) before previewing.');
                }
                $preview = $this->sms()->bulkPreview($i, $input['audience'], $input, $input['message']);
            } catch (DomainException $e) {
                $error = $e->getMessage();
            }
        }
        return $this->page('bulk', 'Bulk SMS', 'sms-bulk', [
            'input' => $input, 'preview' => $preview, 'previewError' => $error,
            'templates' => $this->sms()->templates->all($i, true), 'variables' => SmsTemplateRenderer::VARIABLES,
            'classes' => $this->sms()->recipients->classes($i), 'sections' => $this->sms()->recipients->sections($i), 'exams' => $this->sms()->recipients->exams($i),
            'filters' => SmsRecipients::FILTERS, 'confirmThreshold' => SmsDispatcher::CONFIRM_THRESHOLD, 'usage' => $this->sms()->queue->usage($i),
        ]);
    }

    public function storeBulk(Request $r): Response
    {
        if ($x = $this->deny('sms.bulk_send')) return $x;
        $input = $this->bulkInput($r);
        $query = http_build_query(array_filter($input + ['preview' => 1], static fn($v) => $v !== '' && $v !== 0));
        try {
            if ($input['send_mode'] === 'schedule' && trim($input['scheduled_at']) === '') {
                throw new DomainException('Choose the date and time to send this batch.');
            }
            $result = $this->sms()->bulkQueue($this->i(), $input['audience'], $input, $input['message'], [
                'confirmed' => (bool) $r->input('confirm'), 'priority' => $input['priority'], 'template_id' => $input['template_id'],
                'scheduled_at' => $input['send_mode'] === 'schedule' ? $input['scheduled_at'] : null,
            ], (int) user()['id']);
        } catch (DomainException $e) {
            return $this->back('/admin/sms/bulk?' . $query, 'error', $e->getMessage());
        }
        $what = $input['send_mode'] === 'schedule' ? 'scheduled' : 'queued for the SMS gateway';
        return $this->back('/admin/sms/queue?batch=' . $result['batch_id'], 'success', "{$result['queued']} messages {$what}." . ($result['skipped'] ? " {$result['skipped']} recipients were skipped." : ''));
    }

    /** JSON recipient count, used by the announcement form before queueing announcement SMS. */
    public function count(Request $r): Response
    {
        if (!can('sms.bulk_send')) return Response::json(['error' => 'forbidden'], 403);
        try {
            $p = $this->sms()->bulkPreview($this->i(), (string) $r->query('audience', 'parents'), ['filter' => 'all'], '{{school_name}}');
        } catch (DomainException $e) {
            return Response::json(['error' => $e->getMessage()], 422);
        }
        return Response::json(['total' => $p['total'], 'valid' => count($p['valid']), 'skipped' => count($p['skipped'])]);
    }

    // ---- templates -----------------------------------------------------------------------

    public function templates(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        return $this->page('templates', 'SMS Templates', 'sms-templates', ['templates' => $this->sms()->templates->all($this->i()), 'categories' => SmsTemplates::CATEGORIES]);
    }

    public function templateForm(Request $r): Response
    {
        if ($x = $this->deny('sms.templates.manage')) return $x;
        $id = (int) $r->route('id', 0);
        $template = $id ? $this->sms()->templates->find($this->i(), $id) : null;
        if ($id && !$template) {
            return $this->back('/admin/sms/templates', 'error', 'Template not found.');
        }
        $old = (array) ($this->app->session()->pull('sms_old') ?? []);
        return $this->page('template-form', $template ? 'Edit SMS Template' : 'New SMS Template', 'sms-templates', [
            'template' => $old ? array_replace($template ?? [], $old) : $template, 'templateId' => $id,
            'categories' => SmsTemplates::CATEGORIES, 'variables' => SmsTemplateRenderer::VARIABLES,
        ]);
    }

    public function saveTemplate(Request $r): Response
    {
        if ($x = $this->deny('sms.templates.manage')) return $x;
        $id = (int) $r->route('id', 0);
        $input = ['name' => (string) $r->input('name'), 'category' => (string) $r->input('category'), 'message_body' => (string) $r->input('message_body'), 'is_active' => (bool) $r->input('is_active')];
        try {
            $this->sms()->templates->save($this->i(), $id ?: null, $input, (int) user()['id']);
        } catch (DomainException $e) {
            $this->app->session()->flash('sms_old', $input);
            return $this->back($id ? "/admin/sms/templates/{$id}/edit" : '/admin/sms/templates/create', 'error', $e->getMessage());
        }
        return $this->back('/admin/sms/templates', 'success', 'Template saved.');
    }

    public function deleteTemplate(Request $r): Response
    {
        if ($x = $this->deny('sms.templates.manage')) return $x;
        try {
            $this->sms()->templates->delete($this->i(), (int) $r->route('id'));
        } catch (DomainException $e) {
            return $this->back('/admin/sms/templates', 'error', $e->getMessage());
        }
        return $this->back('/admin/sms/templates', 'success', 'Template deleted.');
    }

    // ---- queue views ---------------------------------------------------------------------

    private const VIEWS = [
        'queue' => ['SMS Queue', 'sms-queue', [], 'All messages, newest first.'],
        'sent' => ['Sent Messages', 'sms-sent', ['sent'], 'Messages a gateway device has confirmed as sent.'],
        'failed' => ['Failed Messages', 'sms-failed', ['failed'], 'Messages that could not be sent. Retry puts them back in the queue.'],
        'scheduled' => ['Scheduled Messages', 'sms-scheduled', ['scheduled'], 'Messages waiting for their scheduled time.'],
    ];

    public function listing(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        $view = (string) $r->route('view', 'queue');
        if (!isset(self::VIEWS[$view])) {
            $view = 'queue';
        }
        [$title, $sub, $statuses, $intro] = self::VIEWS[$view];
        $filters = ['status' => (string) $r->query('status', ''), 'type' => (string) $r->query('type', ''), 'source' => (string) $r->query('source', ''),
            'device' => (int) $r->query('device', 0), 'q' => trim((string) $r->query('q', '')), 'from' => (string) $r->query('from', ''), 'to' => (string) $r->query('to', '')];
        $search = $filters + ['order' => $view === 'queue' ? '' : $view];
        if ($statuses) {
            $search['statuses'] = $statuses;
        }
        $batch = (string) $r->query('batch', '');
        $page = max(1, (int) $r->query('page', 1));
        $result = $batch !== '' && preg_match('/^[a-f0-9]{16}$/', $batch) ? $this->batch($batch, $page) : $this->sms()->queue->search($this->i(), $search, $page, 25);
        return $this->page('listing', $title, $sub, [
            'listView' => $view, 'intro' => $intro, 'rows' => $result['rows'], 'total' => $result['total'], 'page' => $page, 'perPage' => 25,
            'filters' => $filters, 'batch' => $batch, 'devices' => $this->gateway()->all($this->i()),
            'statuses' => SmsQueue::STATUSES, 'types' => SmsQueue::TYPES, 'sources' => SmsQueue::SOURCES,
        ]);
    }

    private function batch(string $batch, int $page): array
    {
        $db = $this->app->database();
        $total = (int) $db->query('SELECT COUNT(*) FROM sms_messages WHERE institute_id=:i AND batch_id=:b', ['i' => $this->i(), 'b' => $batch])->fetchColumn();
        $offset = ($page - 1) * 25;
        $rows = $db->query("SELECT m.*,d.device_name,u.name created_by_name FROM sms_messages m LEFT JOIN sms_gateway_devices d ON d.id=m.gateway_device_id LEFT JOIN users u ON u.id=m.created_by WHERE m.institute_id=:i AND m.batch_id=:b ORDER BY m.id LIMIT 25 OFFSET {$offset}",
            ['i' => $this->i(), 'b' => $batch])->fetchAll();
        return ['rows' => $rows, 'total' => $total];
    }

    public function message(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        $m = $this->sms()->queue->find($this->i(), (int) $r->route('id'));
        if (!$m) {
            return $this->back('/admin/sms/queue', 'error', 'Message not found.');
        }
        $logs = can('sms.logs.view') ? $this->app->database()->query('SELECT * FROM sms_logs WHERE institute_id=:i AND sms_message_id=:m ORDER BY id', ['i' => $this->i(), 'm' => $m['id']])->fetchAll() : [];
        return $this->page('message', 'SMS #' . $m['id'], 'sms-queue', ['message' => $m, 'logs' => $logs, 'text' => SmsText::analyse((string) $m['message'])]);
    }

    public function messageAction(Request $r): Response
    {
        if ($x = $this->deny('sms.queue.manage')) return $x;
        $id = (int) $r->route('id');
        $action = (string) $r->route('action');
        $return = $this->returnTo($r, '/admin/sms/messages/' . $id);
        try {
            match ($action) {
                'cancel' => $this->sms()->queue->cancel($this->i(), $id),
                'retry', 'requeue' => $this->sms()->queue->retry($this->i(), $id),
                'delete' => $this->sms()->queue->delete($this->i(), $id),
                'reschedule' => $this->sms()->queue->updateScheduled($this->i(), $id, (string) $r->input('message'), (string) $r->input('scheduled_at')),
                default => throw new DomainException('Unknown action.'),
            };
        } catch (DomainException $e) {
            return $this->back($return, 'error', $e->getMessage());
        }
        $done = ['cancel' => 'Message cancelled.', 'retry' => 'Message returned to the queue.', 'requeue' => 'Message returned to the queue.', 'delete' => 'Message deleted.', 'reschedule' => 'Scheduled message updated.'][$action];
        return $this->back($action === 'delete' && str_starts_with($return, '/admin/sms/messages/') ? '/admin/sms/queue' : $return, 'success', $done);
    }

    // ---- automations ---------------------------------------------------------------------

    public function automations(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        return $this->page('automations', 'SMS Automations', 'sms-automations', [
            'rules' => (new SmsAutomation($this->app->database()))->rules($this->i()), 'events' => SmsAutomation::EVENTS,
            'templates' => $this->sms()->templates->all($this->i()),
        ]);
    }

    public function saveAutomation(Request $r): Response
    {
        if ($x = $this->deny('sms.settings.manage')) return $x;
        try {
            (new SmsAutomation($this->app->database()))->saveRule($this->i(), (string) $r->route('event'), [
                'enabled' => $r->input('enabled'), 'template_id' => $r->input('template_id'), 'statuses' => (array) $r->input('statuses', []),
                'days_before' => $r->input('days_before'), 'days_after' => $r->input('days_after'),
            ], (int) user()['id']);
        } catch (DomainException $e) {
            return $this->back('/admin/sms/automations', 'error', $e->getMessage());
        }
        return $this->back('/admin/sms/automations', 'success', 'Automation saved.');
    }

    // ---- gateway devices -----------------------------------------------------------------

    public function devices(Request $r): Response
    {
        if ($x = $this->deny('sms.view')) return $x;
        return $this->page('devices', 'Gateway Devices', 'sms-devices', [
            'devices' => $this->gateway()->all($this->i()),
            'secret' => $this->app->session()->pull('sms_gateway_secret'),
            'apiBase' => url('/api/sms-gateway/v1'),
        ]);
    }

    public function deviceAction(Request $r): Response
    {
        if ($x = $this->deny('sms.gateway.manage')) return $x;
        $i = $this->i();
        $id = (int) $r->route('id', 0);
        $action = (string) $r->route('action', 'create');
        $gw = $this->gateway();
        try {
            switch ($action) {
                case 'create':
                    $id = $gw->create($i, (string) $r->input('device_name'), (string) $r->input('sim_slot'), (int) user()['id']);
                    $code = $gw->issuePairingCode($i, $id, (int) user()['id']);
                    $this->app->session()->flash('sms_gateway_secret', ['device_id' => $id, 'kind' => 'pairing'] + $code);
                    return $this->back('/admin/sms/devices', 'success', 'Device registered. Enter the pairing code in the Android SMS bridge app.');
                case 'update':
                    $gw->rename($i, $id, (string) $r->input('device_name'), (string) $r->input('sim_slot'));
                    $msg = 'Device updated.';
                    break;
                case 'pairing':
                    $code = $gw->issuePairingCode($i, $id, (int) user()['id']);
                    $this->app->session()->flash('sms_gateway_secret', ['device_id' => $id, 'kind' => 'pairing'] + $code);
                    $msg = 'New pairing code generated.';
                    break;
                case 'token':
                    $token = $gw->issueAccessToken($i, $id, (int) user()['id']);
                    $this->app->session()->flash('sms_gateway_secret', ['device_id' => $id, 'kind' => 'token', 'code' => $token]);
                    $msg = 'New access token generated. The previous token no longer works.';
                    break;
                case 'revoke':
                    $gw->revokeTokens($i, $id);
                    $msg = 'All tokens for this device were revoked. It must be paired again.';
                    break;
                case 'disable':
                case 'enable':
                    $gw->setActive($i, $id, $action === 'enable');
                    $msg = $action === 'enable' ? 'Device enabled.' : 'Device disabled. Messages it had reserved were returned to the queue.';
                    break;
                case 'delete':
                    $gw->delete($i, $id);
                    $msg = 'Device removed. Its access was revoked and sent-message history was preserved.';
                    break;
                default:
                    throw new DomainException('Unknown action.');
            }
        } catch (DomainException $e) {
            return $this->back('/admin/sms/devices', 'error', $e->getMessage());
        }
        return $this->back('/admin/sms/devices', 'success', $msg);
    }

    // ---- settings & logs -----------------------------------------------------------------

    public function settings(Request $r): Response
    {
        if ($x = $this->deny('sms.settings.manage')) return $x;
        return $this->page('settings', 'SMS Settings', 'sms-settings', ['devices' => $this->gateway()->all($this->i())]);
    }

    public function saveSettings(Request $r): Response
    {
        if ($x = $this->deny('sms.settings.manage')) return $x;
        try {
            $changed = $this->sms()->settings->save($this->i(), $r->all(), (int) user()['id']);
        } catch (DomainException $e) {
            return $this->back('/admin/sms/settings', 'error', $e->getMessage());
        }
        if ($changed) {
            (new SmsLogger($this->app->database()))->log($this->i(), 'settings_changed', SmsLogger::user(), null, null, ['fields' => $changed]);
        }
        return $this->back('/admin/sms/settings', 'success', 'SMS settings saved.');
    }

    public function logs(Request $r): Response
    {
        if ($x = $this->deny('sms.logs.view')) return $x;
        $where = 'l.institute_id=:i';
        $args = ['i' => $this->i()];
        $event = (string) $r->query('event', '');
        $device = (int) $r->query('device', 0);
        if ($event !== '' && preg_match('/^[a-z_]{2,40}$/', $event)) {
            $where .= ' AND l.event=:e';
            $args['e'] = $event;
        }
        if ($device) {
            $where .= ' AND l.gateway_device_id=:d';
            $args['d'] = $device;
        }
        $page = max(1, (int) $r->query('page', 1));
        $db = $this->app->database();
        $total = (int) $db->query("SELECT COUNT(*) FROM sms_logs l WHERE {$where}", $args)->fetchColumn();
        $offset = ($page - 1) * 50;
        $rows = $db->query("SELECT l.*,d.device_name,m.phone_number FROM sms_logs l LEFT JOIN sms_gateway_devices d ON d.id=l.gateway_device_id LEFT JOIN sms_messages m ON m.id=l.sms_message_id WHERE {$where} ORDER BY l.id DESC LIMIT 50 OFFSET {$offset}", $args)->fetchAll();
        $events = $db->query('SELECT DISTINCT event FROM sms_logs WHERE institute_id=:i ORDER BY event', ['i' => $this->i()])->fetchAll(\PDO::FETCH_COLUMN);
        return $this->page('logs', 'SMS Logs', 'sms-logs', ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => 50, 'events' => $events,
            'selectedEvent' => $event, 'selectedDevice' => $device, 'devices' => $this->gateway()->all($this->i())]);
    }
}
