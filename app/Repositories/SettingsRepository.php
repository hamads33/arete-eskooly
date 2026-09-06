<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SettingsRepository
{
    public function __construct(private readonly Database $db) {}
    public function pdo(): PDO { return $this->db->connection(); }
    public function institute(): array
    {
        $user=auth_user();
        if($user!==null){
            $id=$this->db->query('SELECT institute_id FROM institute_users WHERE user_id=:u',['u'=>(int)$user['id']])->fetchColumn();
            if(!$id&&hasRole('admin')){$single=$this->db->query('SELECT MIN(id) id,COUNT(*) total FROM institutes')->fetch();if((int)($single['total']??0)===1){$id=(int)$single['id'];$this->db->query('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)',['u'=>(int)$user['id'],'i'=>$id,'c'=>date('Y-m-d H:i:s')]);}}
            return $this->db->query('SELECT i.*,p.timezone,p.currency,p.currency_symbol,p.theme,p.language FROM institutes i LEFT JOIN institute_preferences p ON p.institute_id=i.id WHERE i.id=:id',['id'=>(int)$id])->fetch()?:[];
        }
        return $this->db->query('SELECT i.*, p.timezone, p.currency, p.currency_symbol, p.theme, p.language FROM institutes i LEFT JOIN institute_preferences p ON p.institute_id=i.id ORDER BY i.id LIMIT 1')->fetch() ?: [];
    }
    public function updateInstitute(int $id, array $data): void
    {
        $this->db->query('UPDATE institutes SET name=:name,target_line=:target_line,phone=:phone,website=:website,address=:address,country=:country,logo_path=COALESCE(:logo_path,logo_path),updated_at=:updated_at WHERE id=:id', $data + ['id'=>$id,'updated_at'=>date('Y-m-d H:i:s')]);
    }
    public function updatePreferences(int $id, array $data): void
    {
        $this->db->query('UPDATE institute_preferences SET timezone=:timezone,currency=:currency,currency_symbol=:currency_symbol,updated_at=:updated_at WHERE institute_id=:id', $data + ['id'=>$id,'updated_at'=>date('Y-m-d H:i:s')]);
    }
    public function updateThemeLanguage(int$id,string$theme,string$language):void{$this->db->query('UPDATE institute_preferences SET theme=:theme,language=:language,updated_at=:updated WHERE institute_id=:id',['theme'=>$theme,'language'=>$language,'updated'=>date('Y-m-d H:i:s'),'id'=>$id]);}
    public function updateClassFees(int$institute,array$fees):void{$pdo=$this->db->connection();$pdo->beginTransaction();try{foreach($fees as$id=>$amount){$this->db->query('UPDATE classes SET monthly_tuition=:fee,updated_at=:updated WHERE id=:id AND institute_id=:institute',['fee'=>$amount,'updated'=>date('Y-m-d H:i:s'),'id'=>$id,'institute'=>$institute]);}$pdo->commit();}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}
    public function discounts(int$id):array{return$this->db->query('SELECT * FROM discount_types WHERE institute_id=:id ORDER BY name',['id'=>$id])->fetchAll();}
    public function addDiscount(int$id,string$name,string$mode,float$value):void{$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO discount_types(institute_id,name,discount_mode,discount_value,status,created_at,updated_at) VALUES(:i,:n,:m,:v,"active",:c,:u) ON DUPLICATE KEY UPDATE discount_mode=VALUES(discount_mode),discount_value=VALUES(discount_value),updated_at=VALUES(updated_at)',['i'=>$id,'n'=>$name,'m'=>$mode,'v'=>$value,'c'=>$now,'u'=>$now]);}
    public function setDiscountStatus(int$id,int$institute,string$status):bool{return$this->db->query('UPDATE discount_types SET status=:s,updated_at=:u WHERE id=:id AND institute_id=:i',['s'=>$status,'u'=>date('Y-m-d H:i:s'),'id'=>$id,'i'=>$institute])->rowCount()>0;}
    public function banks(int $id): array { return $this->db->query('SELECT * FROM fee_invoice_accounts WHERE institute_id=:id ORDER BY id DESC',['id'=>$id])->fetchAll(); }
    public function addBank(int $id, array $data): void
    {
        $now=date('Y-m-d H:i:s');
        $this->db->query('INSERT INTO fee_invoice_accounts (institute_id,bank_name,account_number,bank_address,instructions,logo_path,created_at,updated_at) VALUES (:institute_id,:bank_name,:account_number,:bank_address,:instructions,:logo_path,:created_at,:updated_at)', $data+['institute_id'=>$id,'created_at'=>$now,'updated_at'=>$now]);
    }
    public function deleteBank(int $id, int $instituteId): ?string
    {
        $row=$this->db->query('SELECT logo_path FROM fee_invoice_accounts WHERE id=:id AND institute_id=:institute',['id'=>$id,'institute'=>$instituteId])->fetch();
        if (!$row) return null;
        $this->db->query('DELETE FROM fee_invoice_accounts WHERE id=:id AND institute_id=:institute',['id'=>$id,'institute'=>$instituteId]);
        return $row['logo_path'];
    }
    public function rules(int $id): array
    {
        $rows=$this->db->query('SELECT audience,content FROM rules WHERE institute_id=:id',['id'=>$id])->fetchAll();
        return array_column($rows,'content','audience');
    }
    public function saveRules(int $id,array $rules): void
    {
        $sql='INSERT INTO rules (institute_id,audience,content,created_at,updated_at) VALUES (:id,:audience,:content,:created,:updated) ON DUPLICATE KEY UPDATE content=VALUES(content),updated_at=VALUES(updated_at)';
        foreach($rules as $audience=>$content) {$now=date('Y-m-d H:i:s');$this->db->query($sql,['id'=>$id,'audience'=>$audience,'content'=>$content,'created'=>$now,'updated'=>$now]);}
    }
    public function grades(int $id): array { return $this->db->query('SELECT * FROM grading_rules WHERE institute_id=:id ORDER BY sort_order,id',['id'=>$id])->fetchAll(); }
    public function replaceGrades(int $id,array $grades): void
    {
        $this->db->query('DELETE FROM grading_rules WHERE institute_id=:id',['id'=>$id]); $now=date('Y-m-d H:i:s');
        foreach($grades as $order=>$g) $this->db->query('INSERT INTO grading_rules (institute_id,grade,range_from,range_to,status,sort_order,created_at,updated_at) VALUES (:id,:grade,:from,:to,:status,:sort,:created,:updated)',['id'=>$id,'grade'=>$g['grade'],'from'=>$g['from'],'to'=>$g['to'],'status'=>$g['status'],'sort'=>$order,'created'=>$now,'updated'=>$now]);
    }
    public function feeParticulars(int $id,array $scope): array
    {
        $sql='SELECT * FROM fee_particulars WHERE institute_id=:id AND month_scope=:month_scope AND target_scope=:target_scope AND month_number <=> :month_number AND target_reference <=> :target_reference ORDER BY id';
        return $this->db->query($sql,$scope+['id'=>$id])->fetchAll();
    }
    public function replaceFeeParticulars(int $id,array $scope,array $items): void
    {
        $params=$scope+['id'=>$id];
        $this->db->query('DELETE FROM fee_particulars WHERE institute_id=:id AND month_scope=:month_scope AND target_scope=:target_scope AND month_number <=> :month_number AND target_reference <=> :target_reference',$params);
        $now=date('Y-m-d H:i:s');
        foreach($items as $item) $this->db->query('INSERT INTO fee_particulars (institute_id,month_scope,month_number,target_scope,target_reference,item_key,label,amount,is_fixed,created_at,updated_at) VALUES (:id,:month_scope,:month_number,:target_scope,:target_reference,:item_key,:label,:amount,:is_fixed,:created,:updated)',$params+$item+['created'=>$now,'updated'=>$now]);
    }
}
