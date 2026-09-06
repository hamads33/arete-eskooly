<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AttendanceRepository;
final class AttendanceService{
public function __construct(private readonly AttendanceRepository$r){}
public function saveStudents(string$date,array$allowed,array$statuses,array$remarks,int$user):void{$rows=[];foreach($allowed as$id){$s=$statuses[$id]??'';if(!in_array($s,['Present','Absent','Leave'],true))throw new \InvalidArgumentException('Invalid student attendance status.');$rows[]=['id'=>(int)$id,'status'=>$s,'remarks'=>trim((string)($remarks[$id]??''))];}$pdo=$this->r->db()->connection();$pdo->beginTransaction();try{$this->r->saveStudents($date,$rows,$user);$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
public function saveEmployees(string$date,array$allowed,array$status,array$in,array$out,array$remarks,int$user):void{$rows=[];foreach($allowed as$id){$s=$status[$id]??'';if(!in_array($s,['Present','Absent','Leave','Late','Half Day'],true))throw new \InvalidArgumentException('Invalid employee attendance status.');$rows[]=['id'=>(int)$id,'status'=>$s,'time_in'=>($in[$id]??'')?:null,'time_out'=>($out[$id]??'')?:null,'remarks'=>trim((string)($remarks[$id]??''))];}$pdo=$this->r->db()->connection();$pdo->beginTransaction();try{$this->r->saveEmployees($date,$rows,$user);$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
}
