<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
final class EmployeeRepository{
public function __construct(private readonly Database $db){}
public function all(int $i,string $q='',bool $activeOnly=false):array{return$this->db->query('SELECT * FROM employees WHERE institute_id=:i AND (name LIKE :q OR employee_no LIKE :q2)'.($activeOnly?" AND status='active'":'').' ORDER BY name ASC,employee_no ASC,id ASC',['i'=>$i,'q'=>'%'.$q.'%','q2'=>'%'.$q.'%'])->fetchAll();}
public function find(int $id,int $i):?array{$r=$this->db->query('SELECT * FROM employees WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetch();return$r?:null;}
public function create(array$d):int{$c=array_keys($d);$this->db->query('INSERT INTO employees('.implode(',',$c).') VALUES (:'.implode(',:',$c).')',$d);return(int)$this->db->connection()->lastInsertId();}
public function update(int$id,int$i,array$d):bool{$s=[];foreach(array_keys($d)as$c)$s[]="$c=:$c";$d+=['id'=>$id,'institute'=>$i];return$this->db->query('UPDATE employees SET '.implode(',',$s).' WHERE id=:id AND institute_id=:institute',$d)->rowCount()>0;}

    /**
     * Deletes an employee only when nothing depends on them.
     *
     * Salary, attendance, homework and teaching history make an employee undeletable on
     * purpose: destroying a paid salary trail would make the finance record untrustworthy.
     * Callers should deactivate instead, which the status workflow already supports.
     *
     * @return string missing | in_use | deleted
     */
    public function deleteEmployee(int $id, int $instituteId): string
    {
        if (!$this->db->query('SELECT 1 FROM employees WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId])->fetchColumn()) {
            return 'missing';
        }
        $blockers = [
            'salary_payments' => 'employee_id',
            'employee_attendance' => 'employee_id',
            'homework' => 'teacher_id',
            'teacher_assignments' => 'employee_id',
            'employee_accounts' => 'employee_id',
            'salary_advances' => 'employee_id',
            'certificates' => 'employee_id',
        ];
        foreach ($blockers as $table => $column) {
            try {
                if ($this->db->query("SELECT 1 FROM {$table} WHERE {$column}=:e LIMIT 1", ['e' => $id])->fetchColumn()) {
                    return 'in_use';
                }
            } catch (\Throwable) {
                // A table that does not exist in this deployment cannot block the delete.
            }
        }
        $this->db->query("DELETE FROM person_status_history WHERE person_type='employee' AND person_id=:id", ['id' => $id]);
        $this->db->query('DELETE FROM employees WHERE id=:id AND institute_id=:i', ['id' => $id, 'i' => $instituteId]);
        return 'deleted';
    }
}
