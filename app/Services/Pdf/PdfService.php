<?php
declare(strict_types=1);
namespace App\Services\Pdf;
use App\Core\Response;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

final class PdfService
{
    public function render(string $view,array $data,array $template,string $filename,bool $download=false): Response
    {
        $settings=json_decode((string)($template['settings_json']??'{}'),true)?:[];
        $configured=array_values(array_filter([trim((string)($settings['signature_one']??'')),trim((string)($settings['signature_two']??''))]));
        if($configured)$data['signatures']=$configured;
        $compact=($template['margin_mode']??'normal')==='compact';$margin=$compact?10:16;
        $tempDir=BASE_PATH.'/storage/tmp/mpdf';
        if(!is_dir($tempDir)&&!mkdir($tempDir,0775,true)&&!is_dir($tempDir))throw new \RuntimeException('Unable to create PDF temporary directory.');
        $mpdf=new Mpdf(['format'=>$template['page_size']??'A4','orientation'=>$template['page_orientation']??'P','margin_left'=>$margin,'margin_right'=>$margin,'margin_top'=>$compact?29:34,'margin_bottom'=>$compact?18:22,'tempDir'=>$tempDir,'default_font'=>'dejavusans']);
        $mpdf->SetTitle((string)($template['title']??'Report'));
        $mpdf->SetAuthor((string)($data['institute']['name']??'Institute'));
        $html=$this->view($view,$data+['template'=>$template]);
        $mpdf->WriteHTML($html);
        $bytes=$mpdf->Output('',Destination::STRING_RETURN);
        return new Response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>($download?'attachment':'inline').'; filename="'.$filename.'"','Content-Length'=>(string)strlen($bytes),'X-Content-Type-Options'=>'nosniff']);
    }
    private function view(string $view,array $data):string{$file=BASE_PATH.'/app/Views/pdf/'.$view.'.php';if(!is_file($file))throw new \RuntimeException('PDF view not found.');extract($data,EXTR_SKIP);ob_start();require$file;return(string)ob_get_clean();}
}
