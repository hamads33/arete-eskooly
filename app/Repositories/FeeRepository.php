<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Core\Database;
final class FeeRepository{
public function __construct(private readonly Database$db){}
public function db():Database{return$this->db;}
public function invoice(int$student,string$month,bool$lock=false):?array{$r=$this->db->query('SELECT * FROM fee_invoices WHERE student_id=:s AND fee_month=:m'.($lock?' FOR UPDATE':''),['s'=>$student,'m'=>$month])->fetch();return$r?:null;}
public function items(int$invoice):array{return$this->db->query('SELECT * FROM fee_invoice_items WHERE fee_invoice_id=:i ORDER BY id',['i'=>$invoice])->fetchAll();}
public function payments(int$invoice):array{return$this->db->query('SELECT p.*,u.name received_by_name,ru.name reversed_by_name FROM fee_payments p JOIN users u ON u.id=p.received_by LEFT JOIN users ru ON ru.id=p.reversed_by WHERE p.fee_invoice_id=:i ORDER BY p.id DESC',['i'=>$invoice])->fetchAll();}
/** Every other month still owing money, so the admin sees arrears before collecting just the current month. */
public function pendingInvoices(int$student,string$excludeMonth=''):array{return$this->db->query('SELECT id,fee_month,invoice_no,remaining_amount,status,due_date FROM fee_invoices WHERE student_id=:s AND remaining_amount>0 AND voided_at IS NULL'.($excludeMonth?' AND fee_month<>:m':'').' ORDER BY fee_month ASC',$excludeMonth?['s'=>$student,'m'=>$excludeMonth]:['s'=>$student])->fetchAll();}
public function previousBalance(int$student,string$month):float{$balance=(float)$this->db->query('SELECT COALESCE(SUM(remaining_amount),0) FROM fee_invoices WHERE student_id=:s AND fee_month<:m',['s'=>$student,'m'=>$month])->fetchColumn();if($balance>0)$this->carryForward($student,$month);return$balance;}
public function outstandingBefore(int$student,string$month):float{return(float)$this->db->query('SELECT COALESCE(SUM(remaining_amount),0) FROM fee_invoices WHERE student_id=:s AND fee_month<:m',['s'=>$student,'m'=>$month])->fetchColumn();}
public function carryForward(int$student,string$month):void{$this->db->query("UPDATE fee_invoices SET remaining_amount=0,status='carried_forward',updated_at=:u WHERE student_id=:s AND fee_month<:m AND remaining_amount>0",['u'=>date('Y-m-d H:i:s'),'s'=>$student,'m'=>$month]);}
public function scopedItems(int$institute,string$month,string$class,string$registration):array{$n=(int)substr($month,5,2);$rows=$this->db->query("SELECT * FROM fee_particulars WHERE institute_id=:i AND (month_scope='all' OR (month_scope='specific' AND month_number=:m)) AND (target_scope='all' OR (target_scope='class' AND target_reference=:c) OR (target_scope='student' AND target_reference=:s)) ORDER BY FIELD(target_scope,'student','class','all'),FIELD(month_scope,'specific','all'),id",['i'=>$institute,'m'=>$n,'c'=>$class,'s'=>$registration])->fetchAll();$out=[];foreach($rows as$r)if(!isset($out[$r['item_key']]))$out[$r['item_key']]=$r;return array_values($out);}
public function createInvoice(array$d,array$items):int{$c=array_keys($d);$this->db->query('INSERT INTO fee_invoices('.implode(',',$c).') VALUES (:'.implode(',:',$c).')',$d);$id=(int)$this->db->connection()->lastInsertId();foreach($items as$item)$this->db->query('INSERT INTO fee_invoice_items(fee_invoice_id,particular,amount,created_at) VALUES (:i,:p,:a,:c)',['i'=>$id,'p'=>$item['particular'],'a'=>$item['amount'],'c'=>date('Y-m-d H:i:s')]);return$id;}
public function addPayment(int$invoice,int$student,float$amount,string$method,?string$reference,int$user):int{$now=date('Y-m-d H:i:s');$receipt='RCT-'.date('YmdHis').'-'.bin2hex(random_bytes(3));$this->db->query('INSERT INTO fee_payments(fee_invoice_id,student_id,receipt_no,payment_date,amount,payment_method,reference,received_by,created_at) VALUES (:i,:s,:r,:d,:a,:m,:ref,:u,:c)',['i'=>$invoice,'s'=>$student,'r'=>$receipt,'d'=>date('Y-m-d'),'a'=>$amount,'m'=>$method,'ref'=>$reference,'u'=>$user,'c'=>$now]);return(int)$this->db->connection()->lastInsertId();}
public function updateInvoice(int$id,float$paid,float$remaining):void{$status=$remaining<=0?'paid':($paid>0?'partial':'unpaid');$this->db->query('UPDATE fee_invoices SET paid_amount=:p,remaining_amount=:r,status=:s,updated_at=:u WHERE id=:id',['p'=>$paid,'r'=>$remaining,'s'=>$status,'u'=>date('Y-m-d H:i:s'),'id'=>$id]);}
public function receipt(int$payment,int$institute):?array{$r=$this->db->query('SELECT p.*,i.*,p.id payment_id,p.amount payment_amount,p.payment_date,p.receipt_no,p.payment_method,p.reference,u.name received_by_name,s.student_name,s.registration_no,s.father_name,s.father_mobile,s.mobile,c.name class_name,ins.name institute_name,ins.address institute_address FROM fee_payments p JOIN fee_invoices i ON i.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id JOIN classes c ON c.id=s.class_id JOIN institutes ins ON ins.id=s.institute_id JOIN users u ON u.id=p.received_by WHERE p.id=:p AND s.institute_id=:i',['p'=>$payment,'i'=>$institute])->fetch();return$r?:null;}
/**
 * Fee payments for the Fees Paid Slip page. Filters: q (student, registration, receipt no., reference),
 * month (fee month), from/to (payment date), method, reversed ('' hide | 'only' | 'all'), student_id.
 */
public function paidSlips(int $i, array $f, int $limit = 200): array
{
    $w = ' WHERE s.institute_id=:i'; $p = ['i' => $i];
    if (($f['q'] ?? '') !== '') { $w .= ' AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR p.receipt_no LIKE :q3 OR p.reference LIKE :q4 OR s.father_name LIKE :q5)'; foreach (['q', 'q2', 'q3', 'q4', 'q5'] as $k) $p[$k] = '%' . $f['q'] . '%'; }
    if (!empty($f['student_id'])) { $w .= ' AND s.id=:sid'; $p['sid'] = (int) $f['student_id']; }
    if (!empty($f['month']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $f['month'])) { $w .= ' AND fi.fee_month=:m'; $p['m'] = $f['month']; }
    foreach (['from' => '>=', 'to' => '<='] as $k => $op) if (!empty($f[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $f[$k])) { $w .= " AND p.payment_date {$op} :{$k}"; $p[$k] = $f[$k]; }
    if (!empty($f['method'])) { $w .= ' AND p.payment_method=:method'; $p['method'] = $f['method']; }
    $rev = (string) ($f['reversed'] ?? '');
    if ($rev === '') $w .= ' AND p.reversed_at IS NULL'; elseif ($rev === 'only') $w .= ' AND p.reversed_at IS NOT NULL';
    return $this->db->query("SELECT p.id,p.receipt_no,p.payment_date,p.amount,p.payment_method,p.reference,p.reversed_at,p.reversal_reason,p.created_at,
            fi.id invoice_id,fi.invoice_no,fi.fee_month,fi.total_amount,fi.remaining_amount,
            s.id student_id,s.student_name,s.registration_no,s.father_name,s.father_mobile,s.mobile,c.name class_name,u.name received_by_name
        FROM fee_payments p JOIN fee_invoices fi ON fi.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id JOIN classes c ON c.id=s.class_id LEFT JOIN users u ON u.id=p.received_by{$w}
        ORDER BY p.payment_date DESC,p.id DESC LIMIT " . max(1, min(1000, $limit)), $p)->fetchAll();
}
/** @return list<string> payment methods actually used, for the filter */
public function paymentMethodsUsed(int $i): array { return $this->db->query('SELECT DISTINCT p.payment_method FROM fee_payments p JOIN students s ON s.id=p.student_id WHERE s.institute_id=:i ORDER BY p.payment_method', ['i' => $i])->fetchAll(\PDO::FETCH_COLUMN); }
/**
 * Fee defaulters grouped by student: every unpaid (non-voided) invoice, the months owed, overdue
 * amounts, last payment and guardian contact. Filters: q, class, month, state (overdue|partial|unpaid),
 * minimum, sort (due|oldest|name|class).
 * @return list<array>
 */
public function defaulters(int $i, array $f): array
{
    $where = ' WHERE s.institute_id=:i AND fi.remaining_amount>0.004 AND fi.voided_at IS NULL';
    $p = ['i' => $i];
    if (!empty($f['month']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $f['month'])) { $where .= ' AND fi.fee_month=:month'; $p['month'] = $f['month']; }
    if (!empty($f['class'])) { $where .= ' AND s.class_id=:class'; $p['class'] = (int) $f['class']; }
    if (($f['q'] ?? '') !== '') { $where .= ' AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR fi.invoice_no LIKE :q3 OR s.father_name LIKE :q4 OR s.mobile LIKE :q5 OR s.father_mobile LIKE :q6)'; foreach (['q', 'q2', 'q3', 'q4', 'q5', 'q6'] as $k) $p[$k] = '%' . $f['q'] . '%'; }
    $rows = $this->db->query("SELECT fi.id invoice_id,fi.invoice_no,fi.fee_month,fi.due_date,fi.total_amount,fi.paid_amount,fi.remaining_amount,fi.previous_balance,fi.status invoice_status,
            s.id student_id,s.student_name,s.registration_no,s.father_name,s.father_mobile,s.mobile,s.status student_status,s.status_changed_at,c.name class_name,
            (SELECT MAX(fp.payment_date) FROM fee_payments fp WHERE fp.student_id=s.id AND fp.reversed_at IS NULL) last_payment
        FROM fee_invoices fi JOIN students s ON s.id=fi.student_id JOIN classes c ON c.id=s.class_id{$where}
        ORDER BY s.student_name,s.id,fi.fee_month", $p)->fetchAll();
    $today = date('Y-m-d');
    $people = [];
    foreach ($rows as $r) {
        $sid = (int) $r['student_id'];
        $people[$sid] ??= ['student_id' => $sid, 'student_name' => $r['student_name'], 'registration_no' => $r['registration_no'], 'class_name' => $r['class_name'],
            'guardian' => $r['father_name'], 'phone' => $r['father_mobile'] ?: $r['mobile'], 'student_status' => $r['student_status'], 'status_changed_at' => $r['status_changed_at'],
            'last_payment' => $r['last_payment'], 'due' => 0.0, 'overdue' => 0.0, 'paid' => 0.0, 'months' => [], 'oldest' => $r['fee_month'], 'partial' => false, 'not_billed' => false];
        $overdue = $r['due_date'] && $r['due_date'] < $today;
        $people[$sid]['months'][] = ['invoice_id' => (int) $r['invoice_id'], 'invoice_no' => $r['invoice_no'], 'month' => $r['fee_month'], 'due_date' => $r['due_date'], 'remaining' => (float) $r['remaining_amount'], 'paid' => (float) $r['paid_amount'], 'previous' => (float) $r['previous_balance'], 'overdue' => $overdue];
        $people[$sid]['due'] += (float) $r['remaining_amount'];
        $people[$sid]['paid'] += (float) $r['paid_amount'];
        if ($overdue) $people[$sid]['overdue'] += (float) $r['remaining_amount'];
        if ((float) $r['paid_amount'] > 0) $people[$sid]['partial'] = true;
        if ($r['fee_month'] < $people[$sid]['oldest']) $people[$sid]['oldest'] = $r['fee_month'];
    }
    // Students with no invoice yet for the chosen month (like eSkooly's month defaulters), at their expected fee.
    if (!empty($f['include_unbilled']) && !empty($p['month'])) {
        $sp = ['i' => $i, 'm' => $p['month'], 'since' => $p['month'] . '-01 00:00:00'];
        $sw = '';
        if (!empty($f['class'])) { $sw .= ' AND s.class_id=:class'; $sp['class'] = (int) $f['class']; }
        if (($f['q'] ?? '') !== '') { $sw .= ' AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR s.father_name LIKE :q3)'; foreach (['q', 'q2', 'q3'] as $k) $sp[$k] = '%' . $f['q'] . '%'; }
        $svc = new \App\Services\FeeService($this, new \App\Repositories\StudentRepository($this->db));
        $unbilled = $this->db->query("SELECT s.*,c.name class_name,c.monthly_tuition FROM students s JOIN classes c ON c.id=s.class_id
            WHERE s.institute_id=:i AND " . \App\Support\PersonStatus::visibleSql($this->db, $i, 'student', 's', 'billing', ':since') . "
            AND NOT EXISTS (SELECT 1 FROM fee_invoices x WHERE x.student_id=s.id AND x.fee_month=:m){$sw} ORDER BY s.student_name LIMIT 20000", $sp)->fetchAll();
        foreach ($unbilled as $s) {
            $sid = (int) $s['id'];
            $pv = $svc->preview($s, $i, $p['month']);
            if ($pv['total'] <= 0.004) continue;
            $people[$sid] ??= ['student_id' => $sid, 'student_name' => $s['student_name'], 'registration_no' => $s['registration_no'], 'class_name' => $s['class_name'],
                'guardian' => $s['father_name'], 'phone' => $s['father_mobile'] ?: $s['mobile'], 'student_status' => $s['status'], 'status_changed_at' => $s['status_changed_at'] ?? null,
                'last_payment' => $this->db->query('SELECT MAX(payment_date) FROM fee_payments WHERE student_id=:s AND reversed_at IS NULL', ['s' => $sid])->fetchColumn() ?: null,
                'due' => 0.0, 'overdue' => 0.0, 'paid' => 0.0, 'months' => [], 'oldest' => $p['month'], 'partial' => false, 'not_billed' => false];
            $people[$sid]['months'][] = ['invoice_id' => 0, 'invoice_no' => null, 'month' => $p['month'], 'due_date' => null, 'remaining' => (float) $pv['total'], 'paid' => 0.0, 'previous' => (float) $pv['previous_balance'], 'overdue' => false, 'not_billed' => true];
            $people[$sid]['due'] += (float) $pv['total'];
            $people[$sid]['not_billed'] = true;
        }
    }
    $min = max(0, (float) ($f['minimum'] ?? 0));
    $state = (string) ($f['state'] ?? '');
    $people = array_values(array_filter($people, static function (array $x) use ($min, $state): bool {
        if ($x['due'] + 0.004 < $min) return false;
        if ($state === 'overdue' && $x['overdue'] <= 0) return false;
        if ($state === 'partial' && !$x['partial']) return false;
        if ($state === 'unpaid' && $x['partial']) return false;
        if ($state === 'unbilled' && !$x['not_billed']) return false;
        return true;
    }));
    $sort = (string) ($f['sort'] ?? 'due');
    usort($people, static fn($a, $b) => match ($sort) {
        'oldest' => [$a['oldest'], -$a['due']] <=> [$b['oldest'], -$b['due']],
        'name' => strcasecmp($a['student_name'], $b['student_name']),
        'class' => [strtolower((string) $a['class_name']), strtolower($a['student_name'])] <=> [strtolower((string) $b['class_name']), strtolower($b['student_name'])],
        default => $b['due'] <=> $a['due'],
    });
    return $people;
}
public function outstanding(int$i,string$month='',int$class=0,string$q='',float$minimum=0):array{$where=' WHERE s.institute_id=:i AND fi.remaining_amount>:minimum AND fi.voided_at IS NULL';$p=['i'=>$i,'minimum'=>max(0,$minimum)];if(preg_match('/^\d{4}-\d{2}$/',$month)){$where.=' AND fi.fee_month=:month';$p['month']=$month;}if($class>0){$where.=' AND s.class_id=:class';$p['class']=$class;}if($q!==''){$where.=' AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR fi.invoice_no LIKE :q3 OR s.father_name LIKE :q4 OR s.mobile LIKE :q5)';foreach(['q','q2','q3','q4','q5']as$k)$p[$k]='%'.$q.'%';}return$this->db->query('SELECT fi.*,s.student_name,s.registration_no,s.father_name,s.mobile,c.name class_name FROM fee_invoices fi JOIN students s ON s.id=fi.student_id JOIN classes c ON c.id=s.class_id'.$where.' ORDER BY fi.fee_month ASC,s.student_name ASC,s.registration_no ASC,s.id ASC',$p)->fetchAll();}
public function report(int$i,string$month='',string$status='',string$q=''):array{$where=' WHERE s.institute_id=:i';$p=['i'=>$i];if(preg_match('/^\d{4}-\d{2}$/',$month)){$where.=' AND fi.fee_month=:month';$p['month']=$month;}if(in_array($status,['unpaid','partial','paid','carried_forward'],true)){$where.=' AND fi.status=:status';$p['status']=$status;}if($q!==''){$where.=' AND (s.student_name LIKE :q OR s.registration_no LIKE :q2 OR fi.invoice_no LIKE :q3)';$p['q']=$p['q2']=$p['q3']='%'.$q.'%';}return$this->db->query('SELECT fi.*,s.student_name,s.registration_no,c.name class_name FROM fee_invoices fi JOIN students s ON s.id=fi.student_id JOIN classes c ON c.id=s.class_id'.$where.' ORDER BY fi.fee_month DESC,fi.id DESC LIMIT 50000',$p)->fetchAll();}
public function deleteInvoice(int$id,int$i):bool{$owned=$this->db->query('SELECT fi.id,(SELECT COUNT(*) FROM fee_payments p WHERE p.fee_invoice_id=fi.id) payment_count FROM fee_invoices fi JOIN students s ON s.id=fi.student_id WHERE fi.id=:id AND s.institute_id=:i',['id'=>$id,'i'=>$i])->fetch();if(!$owned||(int)$owned['payment_count']>0)return false;$pdo=$this->db->connection();$pdo->beginTransaction();try{$this->db->query('DELETE FROM fee_invoice_items WHERE fee_invoice_id=:id',['id'=>$id]);$this->db->query('DELETE FROM fee_invoices WHERE id=:id',['id'=>$id]);$pdo->commit();return true;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
public function payment(int$id,int$i):?array{$row=$this->db->query('SELECT p.*,fi.fee_month,fi.total_amount,fi.invoice_no,fi.voided_at,s.student_name,s.registration_no FROM fee_payments p JOIN fee_invoices fi ON fi.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id WHERE p.id=:id AND s.institute_id=:i',['id'=>$id,'i'=>$i])->fetch();return$row?:null;}
/** Corrects a posted payment and re-derives the invoice balance from every live payment, so the invoice can never be over-paid. */
public function editPayment(int$id,int$i,string$date,float$amount,string$method,?string$reference,?string$remarks):array{$pdo=$this->db->connection();$pdo->beginTransaction();try{$p=$this->db->query('SELECT p.*,fi.total_amount FROM fee_payments p JOIN fee_invoices fi ON fi.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id WHERE p.id=:id AND s.institute_id=:i FOR UPDATE',['id'=>$id,'i'=>$i])->fetch();if(!$p)throw new \DomainException('Payment not found.');if($p['reversed_at'])throw new \DomainException('A reversed payment cannot be edited.');$others=(float)$this->db->query('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE fee_invoice_id=:f AND reversed_at IS NULL AND id<>:id',['f'=>$p['fee_invoice_id'],'id'=>$id])->fetchColumn();if($amount<=0||$others+$amount>(float)$p['total_amount']+0.001)throw new \DomainException('Amount must be greater than zero and cannot exceed the invoice total ('.number_format((float)$p['total_amount']-$others,2).' available).');$this->db->query('UPDATE fee_payments SET payment_date=:d,amount=:a,payment_method=:m,reference=:r,remarks=:rm WHERE id=:id',['d'=>$date,'a'=>$amount,'m'=>$method,'r'=>$reference,'rm'=>$remarks,'id'=>$id]);$paid=$others+$amount;$this->updateInvoice((int)$p['fee_invoice_id'],$paid,max(0,(float)$p['total_amount']-$paid));$pdo->commit();return$p;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
public function reversePayment(int$id,int$i,int$user,string$reason):bool{$pdo=$this->db->connection();$pdo->beginTransaction();try{$p=$this->db->query('SELECT p.*,fi.total_amount FROM fee_payments p JOIN fee_invoices fi ON fi.id=p.fee_invoice_id JOIN students s ON s.id=p.student_id WHERE p.id=:id AND s.institute_id=:i AND p.reversed_at IS NULL FOR UPDATE',['id'=>$id,'i'=>$i])->fetch();if(!$p){$pdo->rollBack();return false;}$this->db->query('UPDATE fee_payments SET reversed_at=:n,reversed_by=:u,reversal_reason=:r WHERE id=:id',['n'=>date('Y-m-d H:i:s'),'u'=>$user,'r'=>mb_substr(trim($reason),0,255),'id'=>$id]);$paid=(float)$this->db->query('SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE fee_invoice_id=:i AND reversed_at IS NULL',['i'=>$p['fee_invoice_id']])->fetchColumn();$this->updateInvoice((int)$p['fee_invoice_id'],$paid,max(0,(float)$p['total_amount']-$paid));$pdo->commit();return true;}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
public function voidInvoice(int$id,int$i,int$user,string$reason):bool{$q=$this->db->query('UPDATE fee_invoices fi JOIN students s ON s.id=fi.student_id SET fi.voided_at=:n,fi.voided_by=:u,fi.void_reason=:r,fi.remaining_amount=0,fi.status="carried_forward",fi.updated_at=:n2 WHERE fi.id=:id AND s.institute_id=:i AND fi.paid_amount=0 AND fi.voided_at IS NULL',['n'=>date('Y-m-d H:i:s'),'u'=>$user,'r'=>mb_substr(trim($reason),0,255),'n2'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$i]);return$q->rowCount()>0;}
}
