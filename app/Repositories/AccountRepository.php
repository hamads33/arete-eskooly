<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
final class AccountRepository{
public function __construct(private readonly Database$db){}
public function defaultHead(int$i):int{$id=(int)$this->db->query("SELECT id FROM account_heads WHERE institute_id=:i AND status='active' ORDER BY id LIMIT 1",['i'=>$i])->fetchColumn();if($id)return$id;$now=date('Y-m-d H:i:s');$this->db->query("INSERT IGNORE INTO account_heads(institute_id,name,type,status,created_at,updated_at) VALUES(:i,'General','both','active',:c,:u)",['i'=>$i,'c'=>$now,'u'=>$now]);$id=(int)$this->db->query("SELECT id FROM account_heads WHERE institute_id=:i AND status='active' ORDER BY id LIMIT 1",['i'=>$i])->fetchColumn();if(!$id)throw new \RuntimeException('No active account head is available.');return$id;}
public function heads(int$i):array{return$this->db->query('SELECT h.*,COUNT(t.id) transaction_count FROM account_heads h LEFT JOIN account_transactions t ON t.account_head_id=h.id WHERE h.institute_id=:i GROUP BY h.id ORDER BY h.name',['i'=>$i])->fetchAll();}
public function addHead(int$i,string$name,string$type):bool{try{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO account_heads(institute_id,name,type,status,created_at,updated_at) VALUES(:i,:n,:t,"active",:c,:u)',['i'=>$i,'n'=>$name,'t'=>$type,'c'=>$now,'u'=>$now]);return true;}catch(\PDOException$e){if($e->getCode()==='23000')return false;throw$e;}}
public function deleteHead(int$id,int$i):string{$head=$this->db->query('SELECT id FROM account_heads WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetchColumn();if(!$head)return'missing';if($this->db->query('SELECT 1 FROM account_transactions WHERE account_head_id=:id LIMIT 1',['id'=>$id])->fetchColumn())return'in_use';$this->db->query('DELETE FROM account_heads WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i]);return'deleted';}
public function create(int$i,string$type,string$date,string$description,float$amount,int$user,?string$reference,?int$head=null):int{$head=$head?:$this->defaultHead($i);$valid=$this->db->query("SELECT 1 FROM account_heads WHERE id=:id AND institute_id=:i AND status='active' AND (type=:t OR type='both')",['id'=>$head,'i'=>$i,'t'=>$type])->fetchColumn();if(!$valid)throw new \InvalidArgumentException('Select a valid account head.');$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO account_transactions(institute_id,transaction_date,description,amount,transaction_type,account_head_id,reference_type,created_by,created_at,updated_at) VALUES (:i,:date,:description,:amount,:type,:head,:reference,:user,:created,:updated)',['i'=>$i,'date'=>$date,'description'=>$description,'amount'=>$amount,'type'=>$type,'head'=>$head,'reference'=>$reference,'user'=>$user,'created'=>$now,'updated'=>$now]);return(int)$this->db->connection()->lastInsertId();}
public function statement(int$i,string$from,string$to,string$q=''):array{return$this->db->query("SELECT t.*,h.name head_name FROM account_transactions t LEFT JOIN account_heads h ON h.id=t.account_head_id WHERE t.institute_id=:i AND t.transaction_date BETWEEN :f AND :t AND (t.description LIKE :q OR COALESCE(t.reference_type,'') LIKE :q2) ORDER BY t.transaction_date,t.id LIMIT 50000",['i'=>$i,'f'=>$from,'t'=>$to,'q'=>'%'.$q.'%','q2'=>'%'.$q.'%'])->fetchAll();}

/**
 * The full money story in one ledger: manually recorded income/expense, fee money coming IN
 * from students, and salary money going OUT to staff — merged and ordered together. Fee and
 * salary payments never had to be re-entered as manual transactions to show up here.
 *
 * @param string $source '' (all), 'manual', 'fee', or 'salary'
 * @param int $head account head id; only applies to manual entries (fee/salary rows have none)
 */
public function find(int$id,int$i):?array{$row=$this->db->query('SELECT * FROM account_transactions WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i])->fetch();return$row?:null;}
public function update(int$id,int$i,string$date,string$description,float$amount,?string$reference,int$head):bool{$t=$this->find($id,$i);if(!$t)return false;$valid=$this->db->query("SELECT 1 FROM account_heads WHERE id=:id AND institute_id=:i AND (type=:t OR type='both')",['id'=>$head,'i'=>$i,'t'=>$t['transaction_type']])->fetchColumn();if(!$valid)throw new \InvalidArgumentException('Select a valid account head.');$this->db->query('UPDATE account_transactions SET transaction_date=:d,description=:n,amount=:a,reference_type=:r,account_head_id=:h,updated_at=:u WHERE id=:id AND institute_id=:i',['d'=>$date,'n'=>$description,'a'=>$amount,'r'=>$reference,'h'=>$head,'u'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$i]);return true;}
public function delete(int$id,int$i):?array{$t=$this->find($id,$i);if(!$t)return null;$this->db->query('DELETE FROM account_transactions WHERE id=:id AND institute_id=:i',['id'=>$id,'i'=>$i]);return$t;}
/** Undo a salary payment: give back any advance it recovered, then remove it so the month can be paid again. */
public function reverseSalary(int$id,int$i):?array{$p=$this->db->query('SELECT sp.* FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id WHERE sp.id=:id AND e.institute_id=:i',['id'=>$id,'i'=>$i])->fetch();if(!$p)return null;$pdo=$this->db->connection();$pdo->beginTransaction();try{foreach($this->db->query('SELECT salary_advance_id,amount FROM salary_advance_recoveries WHERE salary_payment_id=:p',['p'=>$id])->fetchAll()as$r)$this->db->query("UPDATE salary_advances SET recovered_amount=GREATEST(0,recovered_amount-:a),status=IF(status='settled','outstanding',status),updated_at=:u WHERE id=:id",['a'=>$r['amount'],'u'=>date('Y-m-d H:i:s'),'id'=>$r['salary_advance_id']]);$this->db->query('DELETE FROM salary_payments WHERE id=:id',['id'=>$id]);$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}return$p;}
/**
 * What was billed per fee particular (tuition, fine, exam fee, trip…) on invoices for the fee
 * months in range, with collected and still-due totals for those invoices. Fee income itself is
 * recorded when paid (see ledger); this shows which charges make up that income.
 */
public function feeChargesBilled(int$i,string$fromMonth,string$toMonth):array{
    $rows=$this->db->query("SELECT it.particular,COUNT(DISTINCT fi.id) invoices,SUM(it.amount) billed FROM fee_invoice_items it JOIN fee_invoices fi ON fi.id=it.fee_invoice_id JOIN students s ON s.id=fi.student_id WHERE s.institute_id=:i AND fi.voided_at IS NULL AND fi.fee_month BETWEEN :f AND :t GROUP BY it.particular ORDER BY SUM(it.amount) DESC,it.particular",['i'=>$i,'f'=>$fromMonth,'t'=>$toMonth])->fetchAll();
    $tot=$this->db->query("SELECT COUNT(*) invoices,COALESCE(SUM(fi.subtotal),0) billed,COALESCE(SUM(fi.discount),0) discount,COALESCE(SUM(fi.previous_balance),0) previous,COALESCE(SUM(fi.paid_amount),0) collected,COALESCE(SUM(fi.remaining_amount),0) remaining FROM fee_invoices fi JOIN students s ON s.id=fi.student_id WHERE s.institute_id=:i AND fi.voided_at IS NULL AND fi.fee_month BETWEEN :f AND :t",['i'=>$i,'f'=>$fromMonth,'t'=>$toMonth])->fetch();
    return['rows'=>$rows,'totals'=>array_map('floatval',$tot?:[])];
}
public function ledger(int$i,string$from,string$to,string$source='',int$head=0,string$q=''):array{
    $parts=[];$args=[];
    if($source===''||$source==='manual'){
        $sql="SELECT t.transaction_date date,t.description,t.transaction_type type,t.amount,h.name head_name,'manual' source,t.reference_type reference,t.id ref_id,NULL items FROM account_transactions t LEFT JOIN account_heads h ON h.id=t.account_head_id WHERE t.institute_id=:i1 AND t.transaction_date BETWEEN :f1 AND :t1";
        if($head){$sql.=' AND t.account_head_id=:h1';$args['h1']=$head;}
        $parts[]=$sql;$args+=['i1'=>$i,'f1'=>$from,'t1'=>$to];
    }
    if(!$head&&($source===''||$source==='fee')){
        $parts[]="SELECT p.payment_date date,CONCAT('Fee payment — ',s.student_name,' (',c.name,', ',fi.fee_month,')') description,'income' type,p.amount,NULL head_name,'fee' source,p.receipt_no reference,p.id ref_id,(SELECT GROUP_CONCAT(CONCAT(it.particular,' ',FORMAT(it.amount,0)) ORDER BY it.id SEPARATOR ' · ') FROM fee_invoice_items it WHERE it.fee_invoice_id=fi.id) items FROM fee_payments p JOIN fee_invoices fi ON fi.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id JOIN classes c ON c.id=s.class_id WHERE s.institute_id=:i2 AND p.reversed_at IS NULL AND p.payment_date BETWEEN :f2 AND :t2";
        $args+=['i2'=>$i,'f2'=>$from,'t2'=>$to];
    }
    if(!$head&&($source===''||$source==='salary')){
        $parts[]="SELECT sp.payment_date date,CONCAT('Salary — ',e.name,' (',sp.salary_month,')') description,'expense' type,sp.paid_amount amount,NULL head_name,'salary' source,sp.reference reference,sp.id ref_id,NULL items FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id WHERE e.institute_id=:i3 AND sp.payment_date BETWEEN :f3 AND :t3";
        $args+=['i3'=>$i,'f3'=>$from,'t3'=>$to];
    }
    if(!$parts)return[];
    $sql='SELECT * FROM ('.implode(' UNION ALL ',$parts).') ledger';
    if($q!==''){$sql.=" WHERE (description LIKE :q OR COALESCE(reference,'') LIKE :q2 OR COALESCE(items,'') LIKE :q3)";$args['q']=$args['q2']=$args['q3']='%'.$q.'%';}
    $sql.=' ORDER BY date,source,ref_id LIMIT 1000';
    return$this->db->query($sql,$args)->fetchAll();
}

/** One row per day in range with that day's income/expense across all three sources — the trend behind the daily report. */
public function dailyTotals(int$i,string$from,string$to):array{
    $sql="SELECT date,SUM(income) income,SUM(expense) expense FROM (
        SELECT t.transaction_date date,SUM(CASE WHEN t.transaction_type='income' THEN t.amount ELSE 0 END) income,SUM(CASE WHEN t.transaction_type='expense' THEN t.amount ELSE 0 END) expense FROM account_transactions t WHERE t.institute_id=:i1 AND t.transaction_date BETWEEN :f1 AND :t1 GROUP BY t.transaction_date
        UNION ALL
        SELECT p.payment_date date,SUM(p.amount) income,0 expense FROM fee_payments p JOIN students s ON s.id=p.student_id WHERE s.institute_id=:i2 AND p.reversed_at IS NULL AND p.payment_date BETWEEN :f2 AND :t2 GROUP BY p.payment_date
        UNION ALL
        SELECT sp.payment_date date,0 income,SUM(sp.paid_amount) expense FROM salary_payments sp JOIN employees e ON e.id=sp.employee_id WHERE e.institute_id=:i3 AND sp.payment_date BETWEEN :f3 AND :t3 GROUP BY sp.payment_date
    ) daily GROUP BY date ORDER BY date DESC";
    return$this->db->query($sql,['i1'=>$i,'f1'=>$from,'t1'=>$to,'i2'=>$i,'f2'=>$from,'t2'=>$to,'i3'=>$i,'f3'=>$from,'t3'=>$to])->fetchAll();
}

/** Total fee still uncollected right now (not date-ranged — a live snapshot, unlike the ledger). */
public function outstandingCollection(int$i):float{return(float)$this->db->query('SELECT COALESCE(SUM(fi.remaining_amount),0) FROM fee_invoices fi JOIN students s ON s.id=fi.student_id WHERE s.institute_id=:i AND fi.voided_at IS NULL',['i'=>$i])->fetchColumn();}
/** Expense entries between two dates (inclusive), most recent first, optionally narrowed to one account head or a text search. */
public function expensesInRange(int$i,string$from,string$to,int$head=0,string$q=''):array{
    $sql="SELECT t.*,h.name head_name,u.name created_by_name FROM account_transactions t LEFT JOIN account_heads h ON h.id=t.account_head_id LEFT JOIN users u ON u.id=t.created_by WHERE t.institute_id=:i AND t.transaction_type='expense' AND t.transaction_date BETWEEN :f AND :t";
    $p=['i'=>$i,'f'=>$from,'t'=>$to];
    if($head){$sql.=' AND t.account_head_id=:h';$p['h']=$head;}
    if($q!==''){$sql.=" AND (t.description LIKE :q OR COALESCE(t.reference_type,'') LIKE :q2)";$p['q']='%'.$q.'%';$p['q2']='%'.$q.'%';}
    $sql.=' ORDER BY t.transaction_date DESC,t.id DESC LIMIT 1000';
    return$this->db->query($sql,$p)->fetchAll();
}
/** Months (most recent first) that have at least one expense entry, for the report's month picker. */
public function expenseMonths(int$i):array{return$this->db->query("SELECT DISTINCT DATE_FORMAT(transaction_date,'%Y-%m') ym FROM account_transactions WHERE institute_id=:i AND transaction_type='expense' ORDER BY ym DESC",['i'=>$i])->fetchAll(\PDO::FETCH_COLUMN);}
}
