<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\OnlineTestRepository;

/**
 * Applies the admin's "keep uploaded answer images for N days" rule (Online Tests → Upload
 * settings). Only papers the teacher has already marked are cleared, counted from the day the
 * student submitted, so nothing disappears before it has been checked. The marks, feedback and
 * the teacher's own marked copy are kept. Upload files that no record points to (from a
 * submission that was refused) are removed after the same period. 0 days keeps everything.
 */
final class TestUploadCleanup
{
    private const FOLDERS = ['test-papers', 'test-answers'];

    public function __construct(private readonly Database $db) {}

    /** @return int number of files removed */
    public function run(int $institute, ?int $now = null): int
    {
        $now ??= time();
        $days = (new OnlineTestRepository($this->db))->settings($institute)['retention_days'];
        $removed = 0;
        if ($days > 0) {
            $cutoff = date('Y-m-d H:i:s', $now - $days * 86400);
            $attempts = $this->db->query("SELECT a.id,a.submission_files_json FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id
                WHERE t.institute_id=:i AND a.status='marked' AND a.files_purged_at IS NULL AND a.submitted_at IS NOT NULL AND a.submitted_at<:c", ['i' => $institute, 'c' => $cutoff])->fetchAll();
            foreach ($attempts as $a) {
                $files = json_decode((string) $a['submission_files_json'], true) ?: [];
                foreach ($this->db->query('SELECT attachment_path,attachment_paths_json FROM online_test_answers WHERE attempt_id=:a', ['a' => $a['id']])->fetchAll() as $answer) {
                    $files = array_merge($files, json_decode((string) $answer['attachment_paths_json'], true) ?: [], $answer['attachment_path'] ? [$answer['attachment_path']] : []);
                }
                foreach (array_unique($files) as $file) $removed += $this->delete((string) $file);
                $this->db->query('UPDATE online_test_attempts SET files_purged_at=:n WHERE id=:id', ['n' => date('Y-m-d H:i:s', $now), 'id' => $a['id']]);
            }
            $removed += $this->orphans($now - $days * 86400);
        }
        $this->db->query('INSERT INTO online_test_settings(institute_id,last_cleanup_at,last_cleanup_files) VALUES(:i,:n,:f) ON DUPLICATE KEY UPDATE last_cleanup_at=VALUES(last_cleanup_at),last_cleanup_files=VALUES(last_cleanup_files)',
            ['i' => $institute, 'n' => date('Y-m-d H:i:s', $now), 'f' => $removed]);
        return $removed;
    }

    /** Files in the answer folders older than $before that no attempt or answer refers to. */
    private function orphans(int $before): int
    {
        $known = [];
        foreach ($this->db->query('SELECT submission_files_json FROM online_test_attempts WHERE submission_files_json IS NOT NULL')->fetchAll(\PDO::FETCH_COLUMN) as $json) {
            foreach (json_decode((string) $json, true) ?: [] as $f) $known[basename((string) $f)] = true;
        }
        foreach ($this->db->query('SELECT attachment_path,attachment_paths_json FROM online_test_answers WHERE attachment_path IS NOT NULL OR attachment_paths_json IS NOT NULL')->fetchAll() as $row) {
            foreach (array_merge(json_decode((string) $row['attachment_paths_json'], true) ?: [], [$row['attachment_path']]) as $f) if ($f) $known[basename((string) $f)] = true;
        }
        $removed = 0;
        foreach (self::FOLDERS as $folder) {
            foreach (glob(BASE_PATH . '/storage/uploads/' . $folder . '/*') ?: [] as $path) {
                if (is_file($path) && !isset($known[basename($path)]) && filemtime($path) < $before) $removed += $this->delete('storage/uploads/' . $folder . '/' . basename($path));
            }
        }
        return $removed;
    }

    /** Deletes one stored answer file, refusing anything outside the answer upload folders. */
    private function delete(string $relative): int
    {
        if (!preg_match('#^storage/uploads/(' . implode('|', self::FOLDERS) . ')/[A-Za-z0-9._-]+$#', $relative)) return 0;
        $path = BASE_PATH . '/' . $relative;
        return is_file($path) && @unlink($path) ? 1 : 0;
    }
}
