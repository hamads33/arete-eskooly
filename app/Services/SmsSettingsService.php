<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
use DomainException;
final class SmsSettingsService{
public function __construct(private readonly Database$db,private readonly string$key){}
public function save(int$institute,?string$provider,bool$enabled,array$config,?string$secret,int$user):void{if($enabled&&(!$provider||!$secret&&!$this->exists($institute)))throw new DomainException('An enabled gateway requires a provider and secret.');$cipher=null;$last=null;if($secret!==null&&$secret!==''){$cipher=$this->encrypt($secret);$last=substr($secret,-4);}$now=date('Y-m-d H:i:s');$this->db->query('INSERT INTO sms_settings(institute_id,provider,enabled,configuration_json,secret_ciphertext,secret_last_four,updated_by,created_at,updated_at) VALUES(:i,:p,:e,:j,:s,:l,:u,:n,:n2) ON DUPLICATE KEY UPDATE provider=VALUES(provider),enabled=VALUES(enabled),configuration_json=VALUES(configuration_json),secret_ciphertext=COALESCE(VALUES(secret_ciphertext),secret_ciphertext),secret_last_four=COALESCE(VALUES(secret_last_four),secret_last_four),updated_by=VALUES(updated_by),updated_at=VALUES(updated_at)',['i'=>$institute,'p'=>$provider,'e'=>(int)$enabled,'j'=>json_encode($config,JSON_THROW_ON_ERROR),'s'=>$cipher,'l'=>$last,'u'=>$user,'n'=>$now,'n2'=>$now]);}
public function masked(int$institute):?array{$r=$this->db->query('SELECT provider,enabled,configuration_json,secret_last_four FROM sms_settings WHERE institute_id=:i',['i'=>$institute])->fetch();if(!$r)return null;$r['secret_masked']=$r['secret_last_four']?'********'.$r['secret_last_four']:null;unset($r['secret_last_four']);$r['configuration']=json_decode($r['configuration_json'],true,512,JSON_THROW_ON_ERROR);unset($r['configuration_json']);return$r;}
private function exists(int$i):bool{return(bool)$this->db->query('SELECT secret_ciphertext FROM sms_settings WHERE institute_id=:i',['i'=>$i])->fetchColumn();}
private function encrypt(string$value):string{$key=hash('sha256',$this->key,true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new DomainException('Secret encryption failed.');return base64_encode($iv.$tag.$cipher);}
}
