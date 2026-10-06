<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{Database,Session};
use App\Repositories\UserRepository;
final class RememberMeService{
private const COOKIE='arete_remember';
public function __construct(private readonly Database$db,private readonly Session$session,private readonly array$config){}
public function issue(int$userId):void{$selector=bin2hex(random_bytes(16));$validator=bin2hex(random_bytes(32));$seconds=(int)($this->config['remember_lifetime']??2592000);$this->db->query('DELETE FROM remember_tokens WHERE expires_at<=:now',['now'=>date('Y-m-d H:i:s')]);$this->db->query('INSERT INTO remember_tokens(user_id,selector,token_hash,expires_at,created_at) VALUES(:u,:s,:h,:e,:c)',['u'=>$userId,'s'=>$selector,'h'=>hash('sha256',$validator),'e'=>date('Y-m-d H:i:s',time()+$seconds),'c'=>date('Y-m-d H:i:s')]);$this->cookie($selector.':'.$validator,time()+$seconds);}
public function resume():bool{if($this->session->get('auth_user')||empty($_COOKIE[self::COOKIE]))return false;[$selector,$validator]=$this->parts((string)$_COOKIE[self::COOKIE]);if($selector===''||$validator===''){$this->clear();return false;}$row=$this->db->query('SELECT * FROM remember_tokens WHERE selector=:s AND expires_at>:now LIMIT 1',['s'=>$selector,'now'=>date('Y-m-d H:i:s')])->fetch();if(!$row||!hash_equals((string)$row['token_hash'],hash('sha256',$validator))){if($row)$this->db->query('DELETE FROM remember_tokens WHERE id=:id',['id'=>$row['id']]);$this->clear();return false;}$context=(new UserRepository($this->db))->findAuthContext((int)$row['user_id']);if(!$context||!in_array($context['status'],['active','suspended'],true)){$this->db->query('DELETE FROM remember_tokens WHERE id=:id',['id'=>$row['id']]);$this->clear();return false;}$this->db->query('DELETE FROM remember_tokens WHERE id=:id',['id'=>$row['id']]);$this->session->regenerate();$this->session->set('auth_user',$context);$this->issue((int)$context['id']);return true;}
public function revoke():void{[$selector]=$this->parts((string)($_COOKIE[self::COOKIE]??''));if($selector!=='')$this->db->query('DELETE FROM remember_tokens WHERE selector=:s',['s'=>$selector]);$this->clear();}
private function parts(string$value):array{$parts=explode(':',$value,2);return count($parts)===2&&preg_match('/^[a-f0-9]{32}$/',$parts[0])&&preg_match('/^[a-f0-9]{64}$/',$parts[1])?$parts:['',''];}
private function clear():void{$this->cookie('',time()-3600);unset($_COOKIE[self::COOKIE]);}
private function cookie(string$value,int$expires):void{if(headers_sent())return;setcookie(self::COOKIE,$value,['expires'=>$expires,'path'=>'/','secure'=>(bool)$this->config['secure'],'httponly'=>true,'samesite'=>(string)($this->config['same_site']??'Lax')]);if($value!=='')$_COOKIE[self::COOKIE]=$value;}
}
