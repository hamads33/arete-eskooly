<?php
declare(strict_types=1);
namespace App\Services;
use DomainException;

interface WhatsAppProviderInterface{public function configured():bool;public function send(string$phone,string$message):array;}
final class NullWhatsAppProvider implements WhatsAppProviderInterface{public function configured():bool{return false;}public function send(string$phone,string$message):array{return['status'=>'not_configured','reference'=>null];}}
final class WhatsAppService
{
    public function __construct(private readonly WhatsAppProviderInterface$provider=new NullWhatsAppProvider()){}
    public function normalize(string$phone):string{$raw=preg_replace('/[^0-9+]/','',trim($phone))??'';if(str_starts_with($raw,'+'))$raw=substr($raw,1);if(preg_match('/^03\d{9}$/',$raw))$raw='92'.substr($raw,1);elseif(preg_match('/^3\d{9}$/',$raw))$raw='92'.$raw;if(!preg_match('/^[1-9]\d{7,14}$/',$raw))throw new DomainException('Enter a valid mobile number.');return$raw;}
    public function render(string$template,array$values):string{return preg_replace_callback('/\{\{([a-z_]+)\}\}/',static fn($m)=>array_key_exists($m[1],$values)?(string)$values[$m[1]]:'',$template)??'';}
    public function clickUrl(string$phone,string$message):string{return'https://wa.me/'.$this->normalize($phone).'?text='.rawurlencode($message);}
    public function send(string$phone,string$message):array{if(!$this->provider->configured())return['status'=>'not_configured','reference'=>null];return$this->provider->send($this->normalize($phone),$message);}
}
