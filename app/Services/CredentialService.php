<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use DomainException;

final class CredentialService
{
    /** Key material used before APP_KEY was required. Retained for decryption only. */
    private const LEGACY_KEY_MATERIAL = 'arete-managed-credentials';

    public function __construct(private readonly Database$db){}
    public function create(string$type,int$recordId,int$instituteId,string$email,int$creator,?string$password=null):array
    {
        if(!in_array($type,['student','employee','guardian'],true)||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Enter a valid login email.');
        $plain=$this->password($password);$table=$type==='student'?'students':($type==='employee'?'employees':'guardians');$link=$type.'_accounts';$column=$type.'_id';$nameColumn=$type==='student'?'student_name':'name';
        if(!$this->db->query("SELECT 1 FROM {$table} WHERE id=:id AND institute_id=:i",['id'=>$recordId,'i'=>$instituteId])->fetchColumn())throw new DomainException('Record not found.');
        if($this->db->query("SELECT 1 FROM users u WHERE u.email=:e AND NOT EXISTS(SELECT 1 FROM {$link} a WHERE a.user_id=u.id AND a.{$column}=:id)",['e'=>strtolower($email),'id'=>$recordId])->fetchColumn())throw new DomainException('Another account already uses this email.');
        $existing=$this->db->query("SELECT a.user_id FROM {$link} a JOIN {$table} d ON d.id=a.{$column} WHERE a.{$column}=:id AND d.institute_id=:i",['id'=>$recordId,'i'=>$instituteId])->fetchColumn();$now=date('Y-m-d H:i:s');
        // Editing an existing login with no new password changes only its email.
        if($existing&&($password===null||$password==='')){$this->db->query('UPDATE users SET email=:e,updated_at=:u WHERE id=:id',['e'=>strtolower($email),'u'=>$now,'id'=>$existing]);return['user_id'=>(int)$existing,'temporary_password'=>null];}
        $cipher=$type==='guardian'?null:$this->encrypt($plain);
        if($existing){$this->db->query('UPDATE users SET email=:e,password_hash=:p,updated_at=:u WHERE id=:id',['e'=>strtolower($email),'p'=>password_hash($plain,PASSWORD_DEFAULT),'u'=>$now,'id'=>$existing]);if($type!=='guardian')$this->db->query("UPDATE {$link} SET password_cipher=:secret,updated_at=:u WHERE {$column}=:id",['secret'=>$cipher,'u'=>$now,'id'=>$recordId]);$this->grant((int)$existing,$type,$instituteId);return['user_id'=>(int)$existing,'temporary_password'=>$plain];}
        $pdo=$this->db->connection();$pdo->beginTransaction();try{$this->db->query("INSERT INTO users(name,email,password_hash,status,created_at,updated_at) SELECT {$nameColumn},:e,:p,'active',:n,:n2 FROM {$table} WHERE id=:id",['e'=>strtolower($email),'p'=>password_hash($plain,PASSWORD_DEFAULT),'n'=>$now,'n2'=>$now,'id'=>$recordId]);$user=(int)$pdo->lastInsertId();if($type==='guardian')$this->db->query("INSERT INTO {$link}({$column},user_id,created_by,created_at,updated_at) VALUES(:r,:u,:c,:n,:n2)",['r'=>$recordId,'u'=>$user,'c'=>$creator,'n'=>$now,'n2'=>$now]);else$this->db->query("INSERT INTO {$link}({$column},user_id,password_cipher,created_by,created_at,updated_at) VALUES(:r,:u,:secret,:c,:n,:n2)",['r'=>$recordId,'u'=>$user,'secret'=>$cipher,'c'=>$creator,'n'=>$now,'n2'=>$now]);$this->grant($user,$type,$instituteId);$pdo->commit();return['user_id'=>$user,'temporary_password'=>$plain];}catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    /** Admin access change; see AccountAccess for what each level means. */
    public function setStatus(string$type,int$recordId,int$instituteId,string$status,string$reason=''):void
    {
        (new AccountAccess($this->db))->setManual($type,$recordId,$instituteId,$status,$reason);
    }
    /**
     * Removes a portal login while keeping the student or employee record. The user row itself
     * is retired rather than deleted because fees, homework and other history reference it.
     */
    public function delete(string$type,int$recordId,int$instituteId):void
    {
        $user=$this->linkedUser($type,$recordId,$instituteId);$link=$type.'_accounts';$now=date('Y-m-d H:i:s');
        $pdo=$this->db->connection();$pdo->beginTransaction();
        try{
            $this->db->query("DELETE FROM {$link} WHERE user_id=:id",['id'=>$user]);
            $this->db->query('DELETE FROM user_roles WHERE user_id=:id',['id'=>$user]);
            $this->db->query('DELETE FROM institute_users WHERE user_id=:id',['id'=>$user]);
            $this->db->query('DELETE FROM remember_tokens WHERE user_id=:id',['id'=>$user]);
            $this->db->query("UPDATE users SET status='disabled',email=:e,password_hash=:p,updated_at=:u WHERE id=:id",['e'=>'deleted-'.$user.'@deleted.invalid','p'=>password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'u'=>$now,'id'=>$user]);
            $pdo->commit();
        }catch(\Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }
    private function linkedUser(string$type,int$recordId,int$instituteId):int
    {
        if(!in_array($type,['student','employee','guardian'],true))throw new DomainException('Invalid account type.');
        $table=['student'=>'students','employee'=>'employees','guardian'=>'guardians'][$type];$link=$type.'_accounts';$column=$type.'_id';
        $user=(int)$this->db->query("SELECT a.user_id FROM {$link} a JOIN {$table} d ON d.id=a.{$column} WHERE a.{$column}=:id AND d.institute_id=:i",['id'=>$recordId,'i'=>$instituteId])->fetchColumn();
        if($user<=0)throw new DomainException('This record has no login.');
        return$user;
    }
    private function grant(int$userId,string$type,int$instituteId):void{$this->db->query('INSERT IGNORE INTO user_roles(user_id,role_id) SELECT :u,id FROM roles WHERE slug=:slug',['u'=>$userId,'slug'=>$type==='guardian'?'parent':$type]);$this->db->query('INSERT IGNORE INTO institute_users(user_id,institute_id,created_at) VALUES(:u,:i,:c)',['u'=>$userId,'i'=>$instituteId,'c'=>date('Y-m-d H:i:s')]);}
    /**
     * Decrypts with the configured key, then with the pre-APP_KEY fallback key.
     *
     * A deployment that ran without APP_KEY set encrypted under the hardcoded default. Setting a
     * real APP_KEY later must not strand those rows, so the legacy key stays readable — decrypt
     * only. Everything written from here on uses the configured key.
     */
    public function reveal(?string$cipher):?string{if(!$cipher)return null;$raw=base64_decode($cipher,true);if($raw===false||strlen($raw)<29)return null;$iv=substr($raw,0,12);$tag=substr($raw,12,16);$data=substr($raw,28);foreach($this->decryptionKeys() as $key){$plain=openssl_decrypt($data,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($plain!==false)return$plain;}return null;}
    /** @return list<string> Configured key first, then the legacy default when APP_KEY is set. */
    private function decryptionKeys():array{$keys=[$this->key()];$legacy=hash('sha256',self::LEGACY_KEY_MATERIAL,true);if(!in_array($legacy,$keys,true))$keys[]=$legacy;return$keys;}
    private function encrypt(string$plain):string{$iv=random_bytes(12);$tag='';$data=openssl_encrypt($plain,'aes-256-gcm',$this->key(),OPENSSL_RAW_DATA,$iv,$tag);if($data===false)throw new \RuntimeException('Could not protect credential.');return base64_encode($iv.$tag.$data);}
    private function key():string{return hash('sha256',(string)(getenv('APP_KEY')?:self::LEGACY_KEY_MATERIAL),true);}
    private function password(?string$password):string{if($password!==null&&$password!==''){if(strlen($password)<8)throw new DomainException('Password must contain at least 8 characters.');return$password;}return rtrim(strtr(base64_encode(random_bytes(15)),'+/','-_'),'=');}
}
