<?php
declare(strict_types=1);
namespace App\Support;
final class SecureAttachment{
    public static function store(?array$file,string$folder):?string{
        if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        $policy=new UploadPolicy(['application/pdf','image/jpeg','image/png'],['pdf','jpg','jpeg','png'],5242880);
        if(!$policy->validate($file))throw new \DomainException('Only PDF, JPG or PNG files up to 5 MB are allowed.');
        $dir=BASE_PATH.'/storage/uploads/'.$folder;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Upload folder could not be created.');
        $name=$policy->safeFilename(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$name))throw new \RuntimeException('Upload failed.');return'storage/uploads/'.$folder.'/'.$name;
    }
    public static function storeImage(?array$file,string$folder):?string{
        if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return null;
        $policy=new UploadPolicy(['image/jpeg','image/png'],['jpg','jpeg','png'],5242880);
        if(!$policy->validate($file))throw new \DomainException('Only JPG or PNG images up to 5 MB are allowed.');
        $dir=BASE_PATH.'/storage/uploads/'.$folder;if(!is_dir($dir)&&!mkdir($dir,0770,true)&&!is_dir($dir))throw new \RuntimeException('Upload folder could not be created.');
        $name=$policy->safeFilename(pathinfo((string)$file['name'],PATHINFO_EXTENSION));if(!move_uploaded_file((string)$file['tmp_name'],$dir.'/'.$name))throw new \RuntimeException('Upload failed.');return'storage/uploads/'.$folder.'/'.$name;
    }
    public static function nested(?array$files,int|string$key):?array{if(!$files||!isset($files['name'][$key]))return null;return['name'=>$files['name'][$key],'type'=>$files['type'][$key]??'','tmp_name'=>$files['tmp_name'][$key]??'','error'=>$files['error'][$key]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$key]??0];}
    /** Like nested(), but for a grouped multi-file field such as name="answer_attachment[$qid][]" — one question can carry several page photos. */
    public static function nestedList(?array$files,int|string$key):array{
        if(!$files||!isset($files['name'][$key])||!is_array($files['name'][$key]))return[];
        $out=[];
        foreach($files['name'][$key] as$i=>$name){
            if($name==='')continue;
            $out[]=['name'=>$name,'type'=>$files['type'][$key][$i]??'','tmp_name'=>$files['tmp_name'][$key][$i]??'','error'=>$files['error'][$key][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$key][$i]??0];
        }
        return$out;
    }
}
