<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
final class EmployeeRepository{
public function __construct(private readonly Database $db){}
public function all(int $i,string $q='',bool $activeOnly=false):array{return$this->db->query('SELECT * FROM employees WHERE institute_id=:i AND (name LIKE :q OR employee_no LIKE :q2)'.($activeOnly?" AND status='active'":'').' ORDER BY name LIMIT 100',['i'=>$i,'q'=>'%'.$q.'%','q2'=>'%'.$q.'%'])->fetchAll();}
public function find(int $id,int $i):?array{$r=$this->db->query('SELECT * FROM employees WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetch();return$r?:null;}
public function create(array$d):int{$c=array_keys($d);$this->db->query('INSERT INTO employees('.implode(',',$c).') VALUES (:'.implode(',:',$c).')',$d);return(int)$this->db->connection()->lastInsertId();}
public function update(int$id,int$i,array$d):bool{$s=[];foreach(array_keys($d)as$c)$s[]="$c=:$c";$d+=['id'=>$id,'institute'=>$i];return$this->db->query('UPDATE employees SET '.implode(',',$s).' WHERE id=:id AND institute_id=:institute',$d)->rowCount()>0;}
}
