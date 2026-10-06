<?php
declare(strict_types=1);
namespace App\Support;
/**
 * Turns a shared lecture link into something playable inside the portal, so students watch on
 * our own page instead of being sent to YouTube / Drive / Vimeo.
 */
final class LectureMedia{
    /** @return array{provider:string,id:string}|null */
    public static function parse(?string$url):?array{
        $url=trim((string)$url);if($url==='')return null;
        $host=strtolower((string)parse_url($url,PHP_URL_HOST));$path=(string)parse_url($url,PHP_URL_PATH);parse_str((string)parse_url($url,PHP_URL_QUERY),$qs);$m=[];
        if(preg_match('/(^|\.)youtube(-nocookie)?\.com$/',$host)){$id=$qs['v']??(preg_match('#^/(?:embed|shorts|live|v)/([\w-]{6,})#',$path,$m)?$m[1]:null);if(is_string($id)&&preg_match('/^[\w-]{6,20}$/',$id))return['provider'=>'youtube','id'=>$id];}
        if($host==='youtu.be'&&preg_match('#^/([\w-]{6,20})#',$path,$m))return['provider'=>'youtube','id'=>$m[1]];
        if($host==='drive.google.com'){$id=preg_match('#/file/d/([\w-]+)#',$path,$m)?$m[1]:($qs['id']??null);if(is_string($id)&&preg_match('/^[\w-]{10,}$/',$id))return['provider'=>'drive','id'=>$id];}
        if(preg_match('/(^|\.)vimeo\.com$/',$host)&&preg_match('#/(?:video/)?(\d{5,})#',$path,$m))return['provider'=>'vimeo','id'=>$m[1]];
        return null;
    }
    public static function embedUrl(?string$url):?string{
        $p=self::parse($url);if(!$p)return null;
        return match($p['provider']){
            // rel=0 keeps suggestions to this channel; modestbranding/iv_load_policy trim YouTube chrome.
            'youtube'=>'https://www.youtube-nocookie.com/embed/'.$p['id'].'?autoplay=1&rel=0&modestbranding=1&iv_load_policy=3&playsinline=1',
            'drive'=>'https://drive.google.com/file/d/'.$p['id'].'/preview',
            'vimeo'=>'https://player.vimeo.com/video/'.$p['id'].'?autoplay=1&dnt=1&title=0&byline=0&portrait=0',
        };
    }
    /** "3 days ago" style label, as on YouTube. */
    public static function ago(string$date):string{$d=(int)floor((strtotime(date('Y-m-d'))-strtotime($date))/86400);if($d<=0)return'Today';if($d===1)return'Yesterday';if($d<7)return$d.' days ago';if($d<30){$w=intdiv($d,7);return$w.' week'.($w>1?'s':'').' ago';}if($d<365){$m=intdiv($d,30);return$m.' month'.($m>1?'s':'').' ago';}$y=intdiv($d,365);return$y.' year'.($y>1?'s':'').' ago';}
    public static function thumbnail(?string$url):?string{$p=self::parse($url);return$p&&$p['provider']==='youtube'?'https://i.ytimg.com/vi/'.$p['id'].'/hqdefault.jpg':null;}
    public static function providerLabel(?string$url):string{return match(self::parse($url)['provider']??''){'youtube'=>'YouTube','drive'=>'Google Drive','vimeo'=>'Vimeo',default=>'External link'};}
}
