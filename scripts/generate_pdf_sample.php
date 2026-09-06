<?php

declare(strict_types=1);

define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/vendor/autoload.php';

use App\Services\Pdf\{PdfService,ReportTemplateService};

$templates=new ReportTemplateService();
$template=$templates->defaults('report-card');
$data=['institute'=>['name'=>'Arete Coaching Center','address'=>'DHA, Karachi','phone'=>'+92 300 0000000','website'=>'www.arete.edu.pk','logo_path'=>'assets/img/arete-logo.png'],'type'=>'report-card','templateService'=>$templates,'placeholders'=>['institute_name'=>'Arete Coaching Center'],'meta'=>['Student'=>'Ayesha Khan','Registration No.'=>'ACC-2026-014','Class'=>'Grade 10','Exam / Term'=>'First Term 2026','Percentage'=>'91.3%','Grade'=>'A+','Status'=>'Pass'],'columns'=>['Subject'=>'subject','Obtained'=>'obtained','Total'=>'total'],'rows'=>[['subject'=>'Mathematics','obtained'=>'94','total'=>'100'],['subject'=>'English','obtained'=>'89','total'=>'100'],['subject'=>'Physics','obtained'=>'91','total'=>'100']],'summary'=>'Total: 274 / 300','signatures'=>['Class Teacher','Principal']];
$response=(new PdfService())->render('document',$data,$template,'arete-report-card-sample.pdf');
$directory=BASE_PATH.'/output/pdf';if(!is_dir($directory))mkdir($directory,0775,true);
file_put_contents($directory.'/report-engine-sample.pdf',$response->content());
echo $directory.'/report-engine-sample.pdf'.PHP_EOL;
