<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\{FeeRepository,StudentRepository};

final class FeeAutomationService
{
    public function __construct(private readonly FeeRepository$fees,private readonly StudentRepository$students){}
    public function generate(int$institute,string$month,int$user=0,string$source='manual'):array{if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))throw new \InvalidArgumentException('Invalid billing month.');if(!in_array($source,['cron','manual','admission'],true))throw new \InvalidArgumentException('Invalid billing source.');if($user<=0)$user=(int)$this->fees->db()->query('SELECT iu.user_id FROM institute_users iu JOIN user_roles ur ON ur.user_id=iu.user_id JOIN roles r ON r.id=ur.role_id WHERE iu.institute_id=:i AND r.slug="admin" ORDER BY iu.user_id LIMIT 1',['i'=>$institute])->fetchColumn();if($user<=0)throw new \RuntimeException('Institute has no billing administrator.');$rows=$this->students->paginate($institute,'',null,100000);$service=new FeeService($this->fees,$this->students);$made=0;$skipped=0;foreach($rows as$student){if(($student['status']??'')!=='active')continue;if($this->fees->invoice((int)$student['id'],$month)){$skipped++;continue;}$service->ensureInvoice($student,$institute,$month,$user);$made++;}$now=date('Y-m-d H:i:s');$this->fees->db()->query('INSERT INTO fee_generation_runs(institute_id,billing_month,generated_count,skipped_count,triggered_by,source,created_at) VALUES(:i,:m,:g,:s,:u,:source,:c) ON DUPLICATE KEY UPDATE generated_count=VALUES(generated_count),skipped_count=VALUES(skipped_count),triggered_by=VALUES(triggered_by),created_at=VALUES(created_at)',['i'=>$institute,'m'=>$month,'g'=>$made,'s'=>$skipped,'u'=>$user,'source'=>$source,'c'=>$now]);return['generated'=>$made,'skipped'=>$skipped];}
}
