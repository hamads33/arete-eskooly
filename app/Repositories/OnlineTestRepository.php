<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
use DomainException;

/**
 * Online tests: draft → published → students attempt → submitted → teacher marks → teacher
 * publishes the result to the student, the parent, or both.
 *
 * A result is never visible to a student or parent until it has been explicitly published
 * (published_at set): being "marked" internally is not the same as being released.
 */
final class OnlineTestRepository {
    private const VISIBILITY = ['student','parent','both'];

    public function __construct(private readonly Database $db){}

    public function all(int $institute):array{return$this->db->query('SELECT t.*,c.name class_name,su.name subject_name,(SELECT COUNT(*) FROM online_test_questions q WHERE q.test_id=t.id) question_count,(SELECT COUNT(*) FROM online_test_attempts a WHERE a.test_id=t.id AND a.status IN (\'submitted\',\'marked\')) submission_count,(SELECT COUNT(*) FROM online_test_attempts a WHERE a.test_id=t.id AND a.status=\'submitted\') pending_count,(SELECT COUNT(*) FROM online_test_attempts a WHERE a.test_id=t.id AND a.status=\'marked\' AND a.published_at IS NULL) unpublished_count FROM online_tests t JOIN classes c ON c.id=t.class_id LEFT JOIN subjects su ON su.id=t.subject_id WHERE t.institute_id=:i ORDER BY t.id DESC',['i'=>$institute])->fetchAll();}

    public function find(int $institute, int $id): ?array
    {
        $row = $this->db->query('SELECT t.*,c.name class_name,su.name subject_name FROM online_tests t JOIN classes c ON c.id=t.class_id LEFT JOIN subjects su ON su.id=t.subject_id WHERE t.id=:id AND t.institute_id=:i', ['id'=>$id,'i'=>$institute])->fetch();
        return $row ?: null;
    }

    public function create(int $institute,array $data,array $questions,int $user):int{
        $title=trim((string)($data['title']??''));if($title===''||!(int)($data['class_id']??0)||!(int)($data['subject_id']??0)||$questions===[])throw new DomainException('Title, class, subject and at least one question are required.');
        $mode=($data['answer_mode']??'mixed')==='upload_only'?'upload_only':'mixed';
        $opens=$this->date($data['opens_at']??null);$closes=$this->date($data['closes_at']??null);
        if($opens&&$closes&&$opens>=$closes)throw new DomainException('The closing time must be after the opening time.');
        $pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO online_tests(institute_id,class_id,subject_id,title,instructions,answer_mode,opens_at,closes_at,duration_minutes,max_pages,status,created_by,created_at,updated_at) SELECT :i,c.id,cs.subject_id,:t,:n,:m,:o,:x,:d,:p,\'draft\',:u,:now,:now2 FROM classes c JOIN class_subjects cs ON cs.class_id=c.id AND cs.subject_id=:s WHERE c.id=:c AND c.institute_id=:i2',['i'=>$institute,'t'=>$title,'n'=>trim((string)($data['instructions']??''))?:null,'m'=>$mode,'o'=>$opens,'x'=>$closes,'d'=>max(1,(int)($data['duration_minutes']??60)),'p'=>self::pageLimit($data['max_pages']??null),'u'=>$user,'now'=>$now,'now2'=>$now,'c'=>(int)$data['class_id'],'s'=>(int)$data['subject_id'],'i2'=>$institute]);$id=(int)$pdo->lastInsertId();if(!$id)throw new DomainException('Invalid class or subject.');$insert=$pdo->prepare('INSERT INTO online_test_questions(test_id,question_type,question_text,attachment_path,marks,position,options_json,correct_option) VALUES(?,?,?,?,?,?,?,?)');foreach($questions as$p=>$q){$type=($q['type']??'written')==='mcq'?'mcq':'written';$text=trim((string)($q['text']??''));$marks=(float)($q['marks']??0);if(($text===''&&empty($q['attachment']))||$marks<=0)throw new DomainException('Every question needs text or a PDF/image, plus marks.');$options=$type==='mcq'?array_values(array_filter(array_map('trim',(array)($q['options']??[])),fn($v)=>$v!=='')):[];$correct=$type==='mcq'?(int)($q['correct']??-1):null;if($type==='mcq'&&(count($options)<2||!isset($options[$correct])))throw new DomainException('Each MCQ needs at least two options and a correct answer.');$insert->execute([$id,$type,$text,$q['attachment']??null,$marks,$p+1,$type==='mcq'?json_encode($options):null,$correct]);}$pdo->commit();return$id;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}

    public function setStatus(int$i,int$id,string$status):void{if(!in_array($status,['draft','published','closed'],true))throw new DomainException('Invalid status.');if(!$this->db->query('SELECT 1 FROM online_tests WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetchColumn())throw new DomainException('Test not found.');$this->db->query('UPDATE online_tests SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',['s'=>$status,'u'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$i]);}

    public function delete(int $i, int $id): bool
    {
        // A test that nobody has started yet can be removed outright; once a student has an
        // attempt (even in progress) the test is kept so their record is never silently lost.
        if ($this->db->query('SELECT 1 FROM online_test_attempts WHERE test_id=:id', ['id'=>$id])->fetchColumn()) {
            throw new DomainException('This test already has student attempts and cannot be deleted. Close it instead.');
        }
        return $this->db->query('DELETE FROM online_tests WHERE id=:id AND institute_id=:i AND status=\'draft\'', ['id'=>$id,'i'=>$i])->rowCount() > 0;
    }

    /**
     * Tests a student may see: open-and-published tests they can start, plus any test they
     * already have an attempt for (even after it closed or was taken offline), so a result never
     * disappears out from under a student. Marks/total are only included once published to them.
     */
    public function available(array$s):array{
        // Bind PHP's clock rather than SQL NOW(): opens_at/closes_at are stored in the app's
        // configured timezone (Asia/Karachi), but a MySQL server's NOW() follows the OS/session
        // timezone, which on a fresh box defaults to UTC — comparing against NOW() silently
        // shifted every test's window by the UTC/PKT offset. PHP's date_default_timezone_set()
        // (see App::__construct) already makes date() correct, so use that as the single source
        // of truth for "now" instead of asking two different clocks to agree.
        $now = date('Y-m-d H:i:s');
        $rows = $this->db->query(
            "SELECT t.*,su.name subject_name,(SELECT SUM(q.marks) FROM online_test_questions q WHERE q.test_id=t.id) total_marks,
                    a.id attempt_id,a.status attempt_status,a.total_score,a.published_at,a.visible_to,a.started_at,a.teacher_comment
             FROM online_tests t
             LEFT JOIN subjects su ON su.id=t.subject_id
             LEFT JOIN online_test_attempts a ON a.test_id=t.id AND a.student_id=:s
             WHERE t.institute_id=:i AND t.class_id=:c
               AND (
                 (t.status='published' AND (t.opens_at IS NULL OR t.opens_at<=:now1) AND (t.closes_at IS NULL OR t.closes_at>=:now2))
                 OR a.id IS NOT NULL
               )
             ORDER BY t.id DESC",
            ['s'=>$s['id'],'i'=>$s['institute_id'],'c'=>$s['class_id'],'now1'=>$now,'now2'=>$now]
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['result_visible'] = $row['published_at'] !== null && in_array($row['visible_to'], ['student','both'], true);
            if (!$row['result_visible']) {
                $row['total_score'] = null;
                $row['teacher_comment'] = null; // released together with the marks
            }
        }
        return $rows;
    }

    /**
     * $forStart=true enforces the open window (used to begin a brand-new attempt); false allows
     * an existing attempt to be continued, submitted or reviewed regardless of the test's current
     * status or window, so a student is never locked out of work already in progress.
     */
    public function testForStudent(int$id,array$s,bool$forStart=true):?array{
        $sql="SELECT * FROM online_tests WHERE id=:id AND institute_id=:i AND class_id=:c";
        $params=['id'=>$id,'i'=>$s['institute_id'],'c'=>$s['class_id']];
        if($forStart){$sql.=" AND status='published' AND (opens_at IS NULL OR opens_at<=:now1) AND (closes_at IS NULL OR closes_at>=:now2)";$now=date('Y-m-d H:i:s');$params['now1']=$now;$params['now2']=$now;}
        $t=$this->db->query($sql,$params)->fetch();
        if(!$t){
            if(!$forStart)return null;
            // Not open for a fresh start; an existing attempt should still be reachable.
            $has=$this->db->query('SELECT 1 FROM online_test_attempts WHERE test_id=:t AND student_id=:s',['t'=>$id,'s'=>$s['id']])->fetchColumn();
            if(!$has)return null;
            return$this->testForStudent($id,$s,false);
        }
        $t['questions']=$this->questions($id);
        $t['attempt']=$this->db->query('SELECT * FROM online_test_attempts WHERE test_id=:t AND student_id=:s',['t'=>$id,'s'=>$s['id']])->fetch()?:null;
        return$t;
    }

    /** Starts (or resumes) an attempt. Returns null when the test is not reachable at all; an already-submitted attempt is still returned so the caller can show the student their status. */
    public function start(int$id,array$s):?array{
        $test=$this->testForStudent($id,$s,true);
        if(!$test)return null;
        if(!$test['attempt']){
            $this->db->query("INSERT IGNORE INTO online_test_attempts(test_id,student_id,status,started_at) VALUES(:t,:s,'in_progress',:n)",['t'=>$id,'s'=>$s['id'],'n'=>date('Y-m-d H:i:s')]);
            $test=$this->testForStudent($id,$s,false);
        }
        return$test;
    }

    /** Most pages one upload box accepts (the portal's PHP max_file_uploads is 40 per request). */
    public const HARD_MAX_PAGES = 40;

    /** A per-test page limit from a form value: 1–40, or null to use the institute default. */
    public static function pageLimit(mixed $value): ?int
    {
        $n = (int) $value;
        return $n > 0 ? min($n, self::HARD_MAX_PAGES) : null;
    }

    /** Upload rules for an institute (defaults until an admin saves them). */
    public function settings(int $institute): array
    {
        $defaults = ['max_pages' => 20, 'retention_days' => 30, 'last_cleanup_at' => null, 'last_cleanup_files' => 0];
        try {
            $row = $this->db->query('SELECT max_pages,retention_days,last_cleanup_at,last_cleanup_files FROM online_test_settings WHERE institute_id=:i', ['i' => $institute])->fetch();
        } catch (\PDOException) {
            return $defaults; // migration not run yet
        }
        return $row ? ['max_pages' => (int) $row['max_pages'], 'retention_days' => (int) $row['retention_days'], 'last_cleanup_at' => $row['last_cleanup_at'], 'last_cleanup_files' => (int) $row['last_cleanup_files']] : $defaults;
    }

    public function saveSettings(int $institute, int $maxPages, int $retentionDays): void
    {
        if ($maxPages < 1 || $maxPages > self::HARD_MAX_PAGES) throw new DomainException('Maximum pages must be between 1 and ' . self::HARD_MAX_PAGES . '.');
        if ($retentionDays < 0 || $retentionDays > 3650) throw new DomainException('Keep uploaded images for 0 to 3650 days (0 keeps them forever).');
        $this->db->query('INSERT INTO online_test_settings(institute_id,max_pages,retention_days,updated_at) VALUES(:i,:p,:r,:u) ON DUPLICATE KEY UPDATE max_pages=VALUES(max_pages),retention_days=VALUES(retention_days),updated_at=VALUES(updated_at)',
            ['i' => $institute, 'p' => $maxPages, 'r' => $retentionDays, 'u' => date('Y-m-d H:i:s')]);
    }

    /** Changes one test's page limit (null = use the institute default). */
    public function setMaxPages(int $institute, int $id, ?int $maxPages): void
    {
        $this->db->query('UPDATE online_tests SET max_pages=:p,updated_at=:u WHERE id=:id AND institute_id=:i', ['p' => self::pageLimit($maxPages), 'u' => date('Y-m-d H:i:s'), 'id' => $id, 'i' => $institute]);
    }

    /** How many pages a student may upload for this test (per upload box). */
    public function maxPages(array $test): int
    {
        return self::pageLimit($test['max_pages'] ?? null) ?? min($this->settings((int) $test['institute_id'])['max_pages'], self::HARD_MAX_PAGES);
    }

    /** The time allowed for one attempt: the test's duration plus any extra minutes the teacher gave. */
    public static function allowance(array $test, ?array $attempt): int
    {
        return (int) ($test['duration_minutes'] ?? 0) + (int) ($attempt['extra_minutes'] ?? 0);
    }

    /** Whether any student has opened the test (questions can then no longer be added or removed). */
    public function hasAttempts(int $testId): bool
    {
        return (bool) $this->db->query('SELECT 1 FROM online_test_attempts WHERE test_id=:t LIMIT 1', ['t' => $testId])->fetchColumn();
    }

    /** The test with its questions, for the edit screen. */
    public function forEdit(int $institute, int $id): ?array
    {
        $t = $this->find($institute, $id);
        if (!$t) return null;
        $t['questions'] = $this->questions($id);
        $t['has_attempts'] = $this->hasAttempts($id);
        $t['submitted_count'] = (int) $this->db->query("SELECT COUNT(*) FROM online_test_attempts WHERE test_id=:t AND status IN ('submitted','marked')", ['t' => $id])->fetchColumn();
        return $t;
    }

    /**
     * Edits a test. Details (title, instructions, window, time allowed, page limit) and each
     * question's text, marks, file and MCQ options can always be changed. Class, subject, answer
     * mode and adding/removing questions are only allowed before any student has opened the test.
     * When marks or an MCQ answer key change, submitted papers are re-scored to match.
     *
     * @param array $questions [question_id => ['text','marks','options'=>[],'correct','attachment'=>?string,'remove_attachment'=>bool]]
     * @param array $added     new questions (same shape as create())
     * @param list<int> $removed question ids to delete
     * @return int attempts whose score was recalculated
     */
    public function update(int $institute, int $id, array $data, array $questions, array $added = [], array $removed = []): int
    {
        $test = $this->forEdit($institute, $id);
        if (!$test) throw new DomainException('Test not found.');
        $title = trim((string) ($data['title'] ?? $test['title']));
        if ($title === '') throw new DomainException('The test needs a title.');
        // A field that is not sent keeps its current value.
        $has = static fn(string $k): bool => array_key_exists($k, $data);
        $opens = $has('opens_at') ? $this->date($data['opens_at']) : $test['opens_at'];
        $closes = $has('closes_at') ? $this->date($data['closes_at']) : $test['closes_at'];
        if ($opens && $closes && $opens >= $closes) throw new DomainException('The closing time must be after the opening time.');
        $duration = (int) ($has('duration_minutes') ? $data['duration_minutes'] : $test['duration_minutes']);
        $instructions = $has('instructions') ? (trim((string) $data['instructions']) ?: null) : $test['instructions'];
        $maxPages = $has('max_pages') ? self::pageLimit($data['max_pages']) : self::pageLimit($test['max_pages']);
        if ($duration < 1 || $duration > 1440) throw new DomainException('Time allowed must be between 1 and 1440 minutes.');
        $locked = $test['has_attempts'];
        $classId = (int) ($data['class_id'] ?? $test['class_id']); $subjectId = (int) ($data['subject_id'] ?? $test['subject_id']);
        $mode = ($data['answer_mode'] ?? $test['answer_mode']) === 'upload_only' ? 'upload_only' : 'mixed';
        if ($locked && ($classId !== (int) $test['class_id'] || $subjectId !== (int) $test['subject_id'] || $mode !== $test['answer_mode'] || $added || $removed)) {
            throw new DomainException('Students have already opened this test, so its class, subject, answer mode and number of questions can no longer change. You can still edit the details, question wording, marks and files.');
        }
        if (!$locked && !$this->db->query('SELECT 1 FROM classes c JOIN class_subjects cs ON cs.class_id=c.id AND cs.subject_id=:s WHERE c.id=:c AND c.institute_id=:i', ['s' => $subjectId, 'c' => $classId, 'i' => $institute])->fetchColumn()) {
            throw new DomainException('Invalid class or subject.');
        }
        $existing = array_column($test['questions'], null, 'id');
        $removed = array_values(array_intersect(array_map('intval', $removed), array_map('intval', array_keys($existing))));
        if (count($existing) - count($removed) + count($added) < 1) throw new DomainException('A test needs at least one question.');
        $pdo = $this->db->connection();
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $this->db->query('UPDATE online_tests SET title=:t,instructions=:n,class_id=:c,subject_id=:s,answer_mode=:m,opens_at=:o,closes_at=:x,duration_minutes=:d,max_pages=:p,updated_at=:u WHERE id=:id AND institute_id=:i',
                ['t' => $title, 'n' => $instructions, 'c' => $classId, 's' => $subjectId, 'm' => $mode, 'o' => $opens, 'x' => $closes, 'd' => $duration, 'p' => $maxPages, 'u' => $now, 'id' => $id, 'i' => $institute]);
            $regrade = false;
            foreach ($existing as $qid => $q) {
                if (in_array((int) $qid, $removed, true) || !isset($questions[$qid])) continue;
                $in = $questions[$qid];
                $text = trim((string) ($in['text'] ?? $q['question_text']));
                $marks = (float) ($in['marks'] ?? $q['marks']);
                $file = $in['attachment'] ?? null;
                $path = $file ?: (!empty($in['remove_attachment']) ? null : $q['attachment_path']);
                if (($text === '' && !$path) || $marks <= 0) throw new DomainException('Question ' . $q['position'] . ' needs text or a file, plus marks.');
                $options = $q['options']; $correct = $q['correct_option'] !== null ? (int) $q['correct_option'] : null;
                if ($q['question_type'] === 'mcq') {
                    $options = array_values(array_filter(array_map('trim', (array) ($in['options'] ?? $q['options'])), static fn($v) => $v !== ''));
                    $correct = (int) ($in['correct'] ?? $q['correct_option']);
                    if (count($options) < 2 || !isset($options[$correct])) throw new DomainException('Question ' . $q['position'] . ' needs at least two options and a correct answer.');
                    if ($locked && count($options) < count($q['options'])) throw new DomainException('Question ' . $q['position'] . ': options cannot be removed after students have answered; you can reword them or change the correct answer.');
                }
                if ((float) $q['marks'] !== $marks || $correct !== ($q['correct_option'] !== null ? (int) $q['correct_option'] : null)) $regrade = true;
                $this->db->query('UPDATE online_test_questions SET question_text=:t,marks=:m,attachment_path=:a,options_json=:o,correct_option=:c WHERE id=:id AND test_id=:test',
                    ['t' => $text, 'm' => $marks, 'a' => $path, 'o' => $q['question_type'] === 'mcq' ? json_encode($options) : null, 'c' => $q['question_type'] === 'mcq' ? $correct : null, 'id' => $qid, 'test' => $id]);
            }
            foreach ($removed as $qid) $this->db->query('DELETE FROM online_test_questions WHERE id=:id AND test_id=:t', ['id' => $qid, 't' => $id]);
            $position = (int) $this->db->query('SELECT COALESCE(MAX(position),0) FROM online_test_questions WHERE test_id=:t', ['t' => $id])->fetchColumn();
            foreach ($added as $q) {
                $type = ($q['type'] ?? 'written') === 'mcq' ? 'mcq' : 'written';
                $text = trim((string) ($q['text'] ?? '')); $marks = (float) ($q['marks'] ?? 0);
                if (($text === '' && empty($q['attachment'])) || $marks <= 0) throw new DomainException('Every new question needs text or a file, plus marks.');
                $options = $type === 'mcq' ? array_values(array_filter(array_map('trim', (array) ($q['options'] ?? [])), static fn($v) => $v !== '')) : [];
                $correct = $type === 'mcq' ? (int) ($q['correct'] ?? -1) : null;
                if ($type === 'mcq' && (count($options) < 2 || !isset($options[$correct]))) throw new DomainException('Each new MCQ needs at least two options and a correct answer.');
                $this->db->query('INSERT INTO online_test_questions(test_id,question_type,question_text,attachment_path,marks,position,options_json,correct_option) VALUES(:t,:ty,:tx,:a,:m,:p,:o,:c)',
                    ['t' => $id, 'ty' => $type, 'tx' => $text, 'a' => $q['attachment'] ?? null, 'm' => $marks, 'p' => ++$position, 'o' => $type === 'mcq' ? json_encode($options) : null, 'c' => $correct]);
            }
            $rescored = $regrade ? $this->rescore($id) : 0;
            $pdo->commit();
            return $rescored;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Re-applies the current marks/answer key to every submitted paper of a test. */
    private function rescore(int $testId): int
    {
        $this->db->query("UPDATE online_test_answers an JOIN online_test_questions q ON q.id=an.question_id JOIN online_test_attempts a ON a.id=an.attempt_id
            SET an.score=CASE WHEN q.question_type='mcq' AND an.selected_option IS NOT NULL THEN IF(an.selected_option=q.correct_option,q.marks,0) WHEN an.score IS NOT NULL THEN LEAST(an.score,q.marks) ELSE NULL END
            WHERE q.test_id=:t AND a.status IN ('submitted','marked')", ['t' => $testId]);
        return $this->db->query("UPDATE online_test_attempts a SET a.total_score=(SELECT COALESCE(SUM(an.score),0) FROM online_test_answers an WHERE an.attempt_id=a.id) WHERE a.test_id=:t AND a.status='marked'", ['t' => $testId])->rowCount();
    }

    /**
     * Extra time for the whole class: adds minutes to the test's time allowance (students who are
     * mid-test and students who have not started yet both get it) and, if asked, moves the closing
     * time back by the same amount. Returns [new duration, new closes_at, students still writing].
     */
    public function extendTest(int $institute, int $testId, int $minutes, bool $moveClosing): array
    {
        if ($minutes < 1 || $minutes > 600) throw new DomainException('Add between 1 and 600 minutes.');
        $t = $this->find($institute, $testId);
        if (!$t) throw new DomainException('Test not found.');
        $duration = (int) $t['duration_minutes'] + $minutes;
        if ($duration > 1440) throw new DomainException('The time allowed cannot be more than 1440 minutes.');
        $closes = $t['closes_at'];
        if ($moveClosing && $closes) $closes = date('Y-m-d H:i:s', strtotime($closes) + $minutes * 60);
        $this->db->query('UPDATE online_tests SET duration_minutes=:d,closes_at=:c,updated_at=:u WHERE id=:id AND institute_id=:i', ['d' => $duration, 'c' => $closes, 'u' => date('Y-m-d H:i:s'), 'id' => $testId, 'i' => $institute]);
        $writing = (int) $this->db->query("SELECT COUNT(*) FROM online_test_attempts WHERE test_id=:t AND status='in_progress'", ['t' => $testId])->fetchColumn();
        return [$duration, $closes, $writing];
    }

    /** Gives one student extra minutes on their attempt (added to anything given before). */
    public function extendAttempt(int $institute, int $attemptId, int $minutes): array
    {
        if ($minutes < 1 || $minutes > 600) throw new DomainException('Add between 1 and 600 minutes.');
        $a = $this->db->query('SELECT a.*,t.duration_minutes,t.title,s.student_name FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id JOIN students s ON s.id=a.student_id WHERE a.id=:id AND t.institute_id=:i', ['id' => $attemptId, 'i' => $institute])->fetch();
        if (!$a) throw new DomainException('Attempt not found.');
        if ($a['status'] !== 'in_progress') throw new DomainException($a['student_name'] . ' has already submitted this test.');
        if ((int) $a['extra_minutes'] + $minutes > 1440) throw new DomainException('A student can be given at most 1440 extra minutes in total.');
        $this->db->query('UPDATE online_test_attempts SET extra_minutes=extra_minutes+:m WHERE id=:id', ['m' => $minutes, 'id' => $attemptId]);
        $a['extra_minutes'] = (int) $a['extra_minutes'] + $minutes;
        return $a;
    }

    /** Minutes an attempt is past its time allowance (0 while still within time). */
    public static function lateMinutes(?string $startedAt, mixed $durationMinutes, ?string $at = null): int
    {
        if (!$startedAt || !(int) $durationMinutes) return 0;
        $over = (strtotime($at ?? date('Y-m-d H:i:s')) - strtotime($startedAt)) - (int) $durationMinutes * 60;
        return $over > 0 ? (int) ceil($over / 60) : 0;
    }

    /**
     * Checks a submission is allowed BEFORE any file is stored, so a refused submission never
     * leaves orphaned photos behind. After the time allowance a submission is still accepted
     * (shown to the teacher as late) while the test is open; once the test is closed it is refused.
     */
    public function assertSubmittable(int$id,array$s):array{
        $test=$this->testForStudent($id,$s,false);
        if(!$test||!$test['attempt'])throw new DomainException('This test cannot be submitted.');
        if(in_array($test['attempt']['status']??'',['submitted','marked'],true))throw new DomainException('You have already submitted this test.');
        if(self::lateMinutes($test['attempt']['started_at'],self::allowance($test,$test['attempt']))>0){
            $now=date('Y-m-d H:i:s');
            $open=$test['status']==='published'&&($test['closes_at']===null||$test['closes_at']>=$now);
            if(!$open)throw new DomainException('The time allowed for this test has ended and the test is now closed, so it can no longer be submitted. Please contact your teacher.');
        }
        return$test;
    }

    public function submit(int$id,array$s,array$answers,array$attachments=[],array$paperFiles=[]):void{
        $test=$this->assertSubmittable($id,$s);
        $uploadOnly=($test['answer_mode']??'mixed')==='upload_only';
        if($uploadOnly&&!$paperFiles)throw new DomainException('Please upload at least one solved-paper PDF or image.');
        $max=$this->maxPages($test);
        if(count($paperFiles)>$max)throw new DomainException("This test accepts at most {$max} pages. Please remove ".(count($paperFiles)-$max).' page(s) and submit again.');
        foreach($attachments as$paths)if(count((array)$paths)>$max)throw new DomainException("Each answer accepts at most {$max} pages.");
        if(!$uploadOnly)foreach($test['questions']as$q){$value=$answers[$q['id']]??null;if($q['question_type']==='mcq'&&!isset($q['options'][(int)$value]))throw new DomainException('An MCQ answer is invalid.');if($q['question_type']==='written'&&trim((string)$value)===''&&empty($attachments[$q['id']]))throw new DomainException('Please type or upload an answer for every question.');}
        $pdo=$this->db->connection();$pdo->beginTransaction();try{$now=date('Y-m-d H:i:s');$updated=$this->db->query("UPDATE online_test_attempts SET status='submitted',submitted_at=:n,submission_files_json=:f WHERE id=:id AND status='in_progress'",['n'=>$now,'f'=>$paperFiles?json_encode(array_values($paperFiles)):null,'id'=>$test['attempt']['id']])->rowCount();if(!$updated)throw new DomainException('This test cannot be submitted.');$attempt=(int)$test['attempt']['id'];$save=$pdo->prepare('INSERT INTO online_test_answers(attempt_id,question_id,answer_text,attachment_path,attachment_paths_json,selected_option,score) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE answer_text=VALUES(answer_text),attachment_path=VALUES(attachment_path),attachment_paths_json=VALUES(attachment_paths_json),selected_option=VALUES(selected_option),score=VALUES(score)');foreach($test['questions']as$q){$value=$answers[$q['id']]??'';$selected=!$uploadOnly&&$q['question_type']==='mcq'?(int)$value:null;$score=!$uploadOnly&&$q['question_type']==='mcq'&&$selected===(int)$q['correct_option']?(float)$q['marks']:(!$uploadOnly&&$q['question_type']==='mcq'?0:null);$paths=array_values(array_filter((array)($attachments[$q['id']]??[])));$save->execute([$attempt,$q['id'],!$uploadOnly&&$q['question_type']==='written'?trim((string)$value):null,$paths[0]??null,$paths?json_encode($paths):null,$selected,$score]);}$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    public function submissions(int$i,int$id):array{return$this->db->query("SELECT a.*,s.student_name,s.registration_no FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id JOIN students s ON s.id=a.student_id WHERE t.institute_id=:i AND t.id=:id ORDER BY FIELD(a.status,'submitted','marked','in_progress'),a.submitted_at DESC,a.started_at DESC",['i'=>$i,'id'=>$id])->fetchAll();}

    public function attempt(int$i,int$id):?array{
        $a=$this->db->query('SELECT a.*,t.title,t.answer_mode,t.created_by,t.duration_minutes,s.student_name,s.registration_no FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id JOIN students s ON s.id=a.student_id WHERE a.id=:id AND t.institute_id=:i',['id'=>$id,'i'=>$i])->fetch();
        if(!$a)return null;
        $a['submission_files']=json_decode((string)($a['submission_files_json']??''),true)?:[];
        $a['marked_files']=json_decode((string)($a['marked_files_json']??''),true)?:[];
        $a['answers']=$this->db->query('SELECT q.*,an.id answer_id,an.answer_text,an.attachment_path answer_attachment_path,an.attachment_paths_json,an.selected_option,an.score,an.feedback FROM online_test_questions q LEFT JOIN online_test_answers an ON an.question_id=q.id AND an.attempt_id=:a WHERE q.test_id=:t ORDER BY q.position',['a'=>$id,'t'=>$a['test_id']])->fetchAll();
        foreach($a['answers']as&$ans){$paths=json_decode((string)($ans['attachment_paths_json']??''),true);$ans['answer_attachments']=is_array($paths)&&$paths?$paths:($ans['answer_attachment_path']?[$ans['answer_attachment_path']]:[]);}unset($ans);
        return$a;
    }

    /** $comment: the teacher's overall comment to the student (null leaves it unchanged, '' clears it). */
    public function mark(int$i,int$id,array$scores,array$feedback,int$user,array$markedFiles=[],?string$comment=null):void{
        $a=$this->attempt($i,$id);
        if(!$a)throw new DomainException('Submission not found.');
        if(!in_array($a['status'],['submitted','marked'],true))throw new DomainException('This attempt has not been submitted yet.');
        $total=0;
        foreach($a['answers']as$answer){
            $score=max(0,min((float)$answer['marks'],(float)($scores[$answer['id']]??$answer['score']??0)));
            $total+=$score;
            $this->db->query('UPDATE online_test_answers SET score=:s,feedback=:f WHERE id=:id',['s'=>$score,'f'=>trim((string)($feedback[$answer['id']]??''))?:null,'id'=>$answer['answer_id']]);
        }
        $files=$markedFiles?array_merge($a['marked_files'],array_values($markedFiles)):$a['marked_files'];
        $this->db->query("UPDATE online_test_attempts SET status='marked',total_score=:s,marked_at=:n,marked_by=:u,marked_files_json=:f WHERE id=:id",['s'=>$total,'n'=>date('Y-m-d H:i:s'),'u'=>$user,'f'=>$files?json_encode($files):null,'id'=>$id]);
        if($comment!==null){
            $comment=trim($comment);
            if(mb_strlen($comment)>2000)throw new DomainException('Keep the comment under 2000 characters.');
            if($comment!==trim((string)($a['teacher_comment']??'')))$this->db->query('UPDATE online_test_attempts SET teacher_comment=:c,teacher_comment_at=:n WHERE id=:id',['c'=>$comment!==''?$comment:null,'n'=>$comment!==''?date('Y-m-d H:i:s'):null,'id'=>$id]);
        }
    }

    /** @var list<int> attempts released by the last publishResults() call */
    public array $justPublished=[];

    /** Publishes every marked-but-not-yet-published attempt for a test. Re-running with a wider scope also widens who can already see a published result. */
    public function publishResults(int$i,int$testId,string$visibleTo,int$user):int
    {
        if(!in_array($visibleTo,self::VISIBILITY,true))throw new DomainException('Invalid visibility.');
        if(!$this->db->query('SELECT 1 FROM online_tests WHERE id=:id AND institute_id=:i',['id'=>$testId,'i'=>$i])->fetchColumn())throw new DomainException('Test not found.');
        // Remembered so the caller can send the result SMS for these, and only these, attempts.
        $this->justPublished=array_map('intval',$this->db->query("SELECT a.id FROM online_test_attempts a WHERE a.test_id=:id AND a.status='marked' AND a.published_at IS NULL",['id'=>$testId])->fetchAll(\PDO::FETCH_COLUMN));
        return $this->db->query(
            "UPDATE online_test_attempts a JOIN online_tests t ON t.id=a.test_id SET a.published_at=COALESCE(a.published_at,:n),a.visible_to=:v WHERE t.id=:id AND t.institute_id=:i AND a.status='marked'",
            ['n'=>date('Y-m-d H:i:s'),'v'=>$visibleTo,'id'=>$testId,'i'=>$i]
        )->rowCount();
    }

    /** Pulls a single attempt's result back out of view (e.g. a marking mistake was found). */
    public function withdrawResult(int$i,int$attemptId):bool
    {
        return $this->db->query(
            "UPDATE online_test_attempts a JOIN online_tests t ON t.id=a.test_id SET a.published_at=NULL WHERE a.id=:id AND t.institute_id=:i",
            ['id'=>$attemptId,'i'=>$i]
        )->rowCount() > 0;
    }

    public function resultsForStudent(array$s):array{return$this->db->query("SELECT a.id attempt_id,t.title,su.name subject,a.submitted_at,a.total_score,a.teacher_comment,(SELECT SUM(q.marks) FROM online_test_questions q WHERE q.test_id=t.id) total_marks FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id LEFT JOIN subjects su ON su.id=t.subject_id WHERE a.student_id=:s AND t.institute_id=:i AND a.status='marked' AND a.published_at IS NOT NULL AND a.visible_to IN('student','both') ORDER BY a.marked_at DESC",['s'=>$s['id'],'i'=>$s['institute_id']])->fetchAll();}

    /** Average performance across every online test result published to the student, for the whole-picture progress report. */
    public function progressSummary(int$instituteId,int$studentId):array{$row=$this->db->query("SELECT COUNT(*) tests,ROUND(AVG(a.total_score/NULLIF(tm.total_marks,0)*100),2) average_percentage FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id JOIN (SELECT test_id,SUM(marks) total_marks FROM online_test_questions GROUP BY test_id) tm ON tm.test_id=t.id WHERE a.student_id=:s AND t.institute_id=:i AND a.status='marked' AND a.published_at IS NOT NULL",['s'=>$studentId,'i'=>$instituteId])->fetch();return['tests'=>(int)($row['tests']??0),'average_percentage'=>$row['average_percentage']!==null?(float)$row['average_percentage']:null];}

    /** A single result, gated by publish + visibility, scoped to the requesting student. */
    public function resultForStudent(int$attemptId,array$s):?array{
        $a=$this->attempt((int)$s['institute_id'],$attemptId);
        if(!$a||(int)$a['student_id']!==(int)$s['id'])return null;
        if($a['status']!=='marked'||$a['published_at']===null||!in_array($a['visible_to'],['student','both'],true))return null;
        return$a;
    }

    /** The student's own attempt regardless of marking/publish state, so a "not published yet" page can explain why rather than a bare 404. */
    public function ownAttempt(int$attemptId,array$s):?array{
        $a=$this->attempt((int)$s['institute_id'],$attemptId);
        return($a&&(int)$a['student_id']===(int)$s['id'])?$a:null;
    }

    /** A single result for a guardian's child, gated by publish + visibility. */
    public function resultForGuardianChild(int$attemptId,int$instituteId,int$studentId):?array{
        $a=$this->attempt($instituteId,$attemptId);
        if(!$a||(int)$a['student_id']!==$studentId)return null;
        if($a['status']!=='marked'||$a['published_at']===null||!in_array($a['visible_to'],['parent','both'],true))return null;
        return$a;
    }

    /** Published results for a guardian's child, for the child overview page. */
    public function resultsForGuardianChild(int$instituteId,int$studentId):array{return$this->db->query("SELECT a.id attempt_id,t.title,su.name subject,a.submitted_at,a.total_score,a.teacher_comment,(SELECT SUM(q.marks) FROM online_test_questions q WHERE q.test_id=t.id) total_marks FROM online_test_attempts a JOIN online_tests t ON t.id=a.test_id LEFT JOIN subjects su ON su.id=t.subject_id WHERE a.student_id=:s AND t.institute_id=:i AND a.status='marked' AND a.published_at IS NOT NULL AND a.visible_to IN('parent','both') ORDER BY a.marked_at DESC",['s'=>$studentId,'i'=>$instituteId])->fetchAll();}

    private function questions(int$id):array{$rows=$this->db->query('SELECT * FROM online_test_questions WHERE test_id=:id ORDER BY position',['id'=>$id])->fetchAll();foreach($rows as&$q)$q['options']=json_decode((string)$q['options_json'],true)?:[];unset($q);return$rows;}
    private function date(mixed$v):?string{$v=trim((string)$v);return$v===''?null:str_replace('T',' ',$v).(strlen($v)===16?':00':'');}
}
