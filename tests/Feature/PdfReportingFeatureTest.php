<?php

declare(strict_types=1);

use App\Core\App;
use App\Services\Pdf\{PdfService,ReportFilenameService,ReportTemplateService};

return static function (App $app): array {
    $checks=[];$templates=new ReportTemplateService();$names=new ReportFilenameService();
    $checks['pdf filename is filesystem safe']=$names->make('Report / Card',['STU:001'])==='report-card-stu-001.pdf';
    $clean=$templates->sanitizeHtml('<p onclick="bad()">Safe</p><script>alert(1)</script>');
    $checks['pdf template html is sanitized']=str_contains($clean,'<p>Safe</p>')&&!str_contains($clean,'script')&&!str_contains($clean,'onclick');
    try{$templates->validate('report-card',['title'=>'Report','primary_color'=>'red','accent_color'=>'#D9A441']);$checks['pdf template rejects invalid colors']=false;}catch(InvalidArgumentException){$checks['pdf template rejects invalid colors']=true;}
    $template=$templates->defaults('report-card');
    $data=['institute'=>['name'=>'Arete Coaching Center','address'=>'DHA, Karachi','phone'=>'+92 300 0000000','website'=>'arete.example'],'type'=>'report-card','templateService'=>$templates,'placeholders'=>['institute_name'=>'Arete Coaching Center'],'meta'=>['Student'=>'Sample Student','Registration'=>'ART-001','Class'=>'Grade 10'],'columns'=>['Subject'=>'subject','Marks'=>'marks'],'rows'=>[['subject'=>'Mathematics','marks'=>'92 / 100'],['subject'=>'English','marks'=>'88 / 100']],'summary'=>'Result: Distinction','signatures'=>['Class Teacher','Principal']];
    $response=(new PdfService())->render('document',$data,$template,$names->make('sample',['report-card']));
    $checks['pdf response is a valid binary document']=str_starts_with($response->content(),'%PDF-')&&strlen($response->content())>2000;
    $checks['pdf response has safe inline headers']=($response->headers()['Content-Type']??'')==='application/pdf'&&str_starts_with($response->headers()['Content-Disposition']??'','inline; filename="sample-report-card.pdf"');
    return $checks;
};
