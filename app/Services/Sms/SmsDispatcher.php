<?php
declare(strict_types=1);
namespace App\Services\Sms;

use App\Core\Database;
use DomainException;

/**
 * Turns "who" + "what" into queue rows: single compose, bulk batches and automation events all
 * come through here, so rendering, validation and limits behave the same everywhere.
 */
final class SmsDispatcher
{
    /** Bulk sends at or above this size need an explicit confirmation tick. */
    public const CONFIRM_THRESHOLD = 50;

    public readonly SmsSettings $settings;
    public readonly SmsQueue $queue;
    public readonly SmsRecipients $recipients;
    public readonly SmsTemplates $templates;

    public function __construct(private readonly Database $db)
    {
        $this->settings = new SmsSettings($db);
        $this->queue = new SmsQueue($db, $this->settings);
        $this->recipients = new SmsRecipients($db, $this->settings);
        $this->templates = new SmsTemplates($db);
    }

    /**
     * Queues one SMS from the Compose page.
     * @param array{recipient_type:string,recipient_id?:int,phone_number?:string,message:string,template_id?:int,priority?:string,scheduled_at?:?string} $d
     */
    public function compose(int $institute, array $d, int $user): int
    {
        if (!$this->settings->get($institute)['allow_manual_sms']) {
            throw new DomainException('Manual SMS is turned off in SMS Settings.');
        }
        $type = (string) $d['recipient_type'];
        $vars = $this->recipients->instituteVars($institute);
        unset($vars['_currency']);
        $vars['date'] = date('d M Y');
        $name = null;
        $id = null;
        if ($type === 'manual') {
            $phone = trim((string) ($d['phone_number'] ?? ''));
            $name = trim((string) ($d['recipient_name'] ?? '')) ?: null;
            if ($name) {
                $vars['recipient_name'] = $name;
            }
        } elseif (in_array($type, ['student', 'guardian', 'staff'], true)) {
            $r = $this->recipients->resolve($institute, $type, (int) ($d['recipient_id'] ?? 0));
            if (!$r) {
                throw new DomainException('Select a recipient from the list.');
            }
            // The number on record is the default; an edited number is used only when it is valid.
            $phone = trim((string) ($d['phone_number'] ?? '')) ?: (string) $r['raw_phone'];
            if ($phone === '') {
                throw new DomainException("{$r['name']} has no phone number on record. Enter one to send anyway.");
            }
            $name = $r['name'];
            $id = $r['recipient_id'];
            $vars = $r['vars'];
        } else {
            throw new DomainException('Select a recipient type.');
        }
        $message = SmsTemplateRenderer::render((string) $d['message'], $vars);
        $templateId = (int) ($d['template_id'] ?? 0) ?: null;
        if ($templateId && !$this->templates->find($institute, $templateId)) {
            $templateId = null;
        }
        return $this->queue->enqueue($institute, [
            'phone_number' => $phone, 'message' => $message, 'recipient_type' => $type, 'recipient_id' => $id, 'recipient_name' => $name,
            'message_type' => 'manual', 'source' => 'manual', 'priority' => $d['priority'] ?? null, 'template_id' => $templateId,
            'scheduled_at' => $d['scheduled_at'] ?? null, 'created_by' => $user,
        ], SmsLogger::user())['id'];
    }

    /**
     * Renders a bulk batch without queueing it: what the admin reviews before confirming.
     * @return array{total:int,valid:list<array>,skipped:list<array>,duplicates:int}
     */
    public function bulkPreview(int $institute, string $audience, array $filters, string $body): array
    {
        SmsTemplateRenderer::validate($body);
        $valid = [];
        $skipped = [];
        $seen = [];
        $duplicates = 0;
        $entries = $this->recipients->group($institute, $audience, $filters);
        foreach ($entries as $e) {
            if ($e['phone'] === null) {
                $skipped[] = ['name' => $e['name'], 'detail' => $e['detail'], 'phone' => $e['raw_phone'], 'reason' => $e['skip_reason']];
                continue;
            }
            try {
                $message = SmsTemplateRenderer::render($body, $e['vars']);
            } catch (DomainException $ex) {
                $skipped[] = ['name' => $e['name'], 'detail' => $e['detail'], 'phone' => $e['phone'], 'reason' => $ex->getMessage()];
                continue;
            }
            // One parent with two children gets one SMS when the text is the same for both.
            $key = $e['phone'] . "\n" . $message;
            if (isset($seen[$key])) {
                $duplicates++;
                continue;
            }
            $seen[$key] = true;
            $valid[] = $e + ['message' => $message];
        }
        return ['total' => count($entries), 'valid' => $valid, 'skipped' => $skipped, 'duplicates' => $duplicates];
    }

    /** @return array{queued:int,batch_id:string,skipped:int} */
    public function bulkQueue(int $institute, string $audience, array $filters, string $body, array $opts, int $user, string $type = 'general', string $source = 'bulk'): array
    {
        $s = $this->settings->get($institute);
        if ($source === 'bulk' && !$s['allow_bulk_sms']) {
            throw new DomainException('Bulk SMS is turned off in SMS Settings.');
        }
        $preview = $this->bulkPreview($institute, $audience, $filters, $body);
        $count = count($preview['valid']);
        if ($count === 0) {
            throw new DomainException('None of the selected recipients has a usable phone number.');
        }
        if ($count >= self::CONFIRM_THRESHOLD && empty($opts['confirmed'])) {
            throw new DomainException("This will queue {$count} messages. Tick the confirmation box to continue.");
        }
        $this->queue->assertWithinLimits($institute, $count);
        $batch = bin2hex(random_bytes(8));
        $templateId = (int) ($opts['template_id'] ?? 0) ?: null;
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            foreach ($preview['valid'] as $e) {
                $this->queue->enqueue($institute, [
                    'phone_number' => $e['phone'], 'message' => $e['message'], 'recipient_type' => $e['recipient_type'], 'recipient_id' => $e['recipient_id'],
                    'recipient_name' => $e['name'], 'message_type' => $type, 'source' => $source, 'priority' => $opts['priority'] ?? null,
                    'template_id' => $templateId, 'batch_id' => $batch, 'scheduled_at' => $opts['scheduled_at'] ?? null, 'created_by' => $user,
                    'dedupe_key' => !empty($opts['dedupe_prefix']) ? $opts['dedupe_prefix'] . ':' . $e['phone'] : null, 'skip_limits' => true,
                ], SmsLogger::user());
            }
            $pdo->commit();
        } catch (\Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $ex;
        }
        return ['queued' => $count, 'batch_id' => $batch, 'skipped' => count($preview['skipped'])];
    }
}
