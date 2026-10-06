<?php
declare(strict_types=1);
use App\Repositories\OnlineTestRepository;
return static function (\App\Core\App $app): array {
    $db=$app->database(); $checks=[];
    $fixture=$db->query("SELECT s.id,s.class_id,c.institute_id,cs.subject_id FROM students s JOIN classes c ON c.id=s.class_id JOIN class_subjects cs ON cs.class_id=c.id WHERE s.status='active' LIMIT 1")->fetch();
    $userId=(int)$db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    if(!$fixture||!$userId)return ['online test fixture is available'=>false];
    $id=0; $uploadId=0;
    try {
        $repo=new OnlineTestRepository($db);
        $student=['id'=>(int)$fixture['id'],'class_id'=>(int)$fixture['class_id'],'institute_id'=>(int)$fixture['institute_id']];
        $id=$repo->create($student['institute_id'],['title'=>'E2E Online Test '.bin2hex(random_bytes(3)),'class_id'=>$student['class_id'],'subject_id'=>(int)$fixture['subject_id'],'duration_minutes'=>30],[
            ['type'=>'mcq','text'=>'Two plus two?','marks'=>2,'options'=>['3','4','5'],'correct'=>1],
            ['type'=>'written','text'=>'Explain your answer.','marks'=>3],
        ],$userId);
        $checks['admin creates class-specific mixed-question test']=$id>0;
        $repo->setStatus($student['institute_id'],$id,'published');
        $checks['published test is visible to assigned class']=count(array_filter($repo->available($student),fn($t)=>(int)$t['id']===$id))===1;
        $test=$repo->start($id,$student);
        $checks['student starts test and receives both question types']=$test&&count($test['questions'])===2;
        $checks['teacher can see that student attempt is still in progress']=count($repo->submissions($student['institute_id'],$id))===1&&$repo->submissions($student['institute_id'],$id)[0]['status']==='in_progress';
        $repo->submit($id,$student,[$test['questions'][0]['id']=>1,$test['questions'][1]['id']=>'Because 2 + 2 equals 4.']);
        $submissions=$repo->submissions($student['institute_id'],$id);
        $checks['student submission reaches admin']=count($submissions)===1&&$submissions[0]['status']==='submitted';
        $attempt=$repo->attempt($student['institute_id'],(int)$submissions[0]['id']);
        $scores=[];foreach($attempt['answers']as$a)$scores[$a['id']]=$a['question_type']==='mcq'?2:3;
        $repo->mark($student['institute_id'],(int)$attempt['id'],$scores,[],$userId);
        $marked=$repo->attempt($student['institute_id'],(int)$attempt['id']);
        $checks['admin marks submission end to end']=$marked['status']==='marked'&&(float)$marked['total_score']===5.0;
        $uploadId=$repo->create($student['institute_id'],['title'=>'Upload-only Test '.bin2hex(random_bytes(3)),'class_id'=>$student['class_id'],'subject_id'=>(int)$fixture['subject_id'],'duration_minutes'=>30,'answer_mode'=>'upload_only'],[
            ['type'=>'written','text'=>'Solve the attached paper.','marks'=>10],
        ],$userId);
        $repo->setStatus($student['institute_id'],$uploadId,'published');
        $uploadTest=$repo->start($uploadId,$student);
        $repo->submit($uploadId,$student,[],[],['storage/uploads/test-papers/example.pdf']);
        $uploadSubmission=$repo->submissions($student['institute_id'],$uploadId)[0]??null;
        $uploadAttempt=$repo->attempt($student['institute_id'],(int)($uploadSubmission['id']??0));
        $checks['student can submit upload-only solved paper']=$uploadAttempt&&$uploadAttempt['answer_mode']==='upload_only'&&count($uploadAttempt['submission_files'])===1;
        $answer=$uploadAttempt['answers'][0];
        $repo->mark($student['institute_id'],(int)$uploadAttempt['id'],[$answer['id']=>8],[$answer['id']=>'Good work'],$userId);
        // Marking alone does not publish; the result must be released before it reaches the student.
        $checks['a marked-but-unpublished result stays out of student progress']=count(array_filter($repo->resultsForStudent($student),fn($row)=>(string)$row['title']===(string)$uploadTest['title']))===0;
        $repo->publishResults($student['institute_id'],$uploadId,'both',$userId);
        $progress=$repo->resultsForStudent($student);
        $checks['teacher marks and publishes upload-only paper into student progress']=count(array_filter($progress,fn($row)=>(string)$row['title']===(string)$uploadTest['title']&&(float)$row['total_score']===8.0))===1;
    } finally { if($uploadId)$db->query('DELETE FROM online_tests WHERE id=:id',['id'=>$uploadId]); if($id)$db->query('DELETE FROM online_tests WHERE id=:id',['id'=>$id]); }
    return $checks;
};
