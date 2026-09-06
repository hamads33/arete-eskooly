<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\SettingsRepository;

final class SettingsService
{
    public const FEE_ITEMS=['monthly_tuition'=>'Monthly Tuition Fee','admission'=>'Admission Fee','registration'=>'Registration Fee','art_material'=>'Art Material','transport'=>'Transport','books'=>'Books','uniform'=>'Uniform','fine'=>'Fine','others'=>'Others','previous_balance'=>'Previous Balance','discount'=>'Discount in Fee'];
    public function __construct(private readonly SettingsRepository $repo) {}
    public function saveFees(int $id,array $scope,array $amounts): void { $items=[];foreach(self::FEE_ITEMS as $key=>$label)$items[]=['item_key'=>$key,'label'=>$label,'amount'=>(float)($amounts[$key]??0),'is_fixed'=>(int)in_array($key,['monthly_tuition','previous_balance','discount'],true)];$pdo=$this->repo->pdo();$pdo->beginTransaction();try{$this->repo->replaceFeeParticulars($id,$scope,$items);$pdo->commit();}catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;} }
    public function sanitizeHtml(string $html): string { $html=preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is','',$html)??'';$html=strip_tags($html,'<p><br><strong><b><em><i><u><ul><ol><li><span>');return preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i','',$html)??''; }
    public function validateGrades(array $labels,array $from,array $to,array $statuses): array { $grades=[];$errors=[];foreach($labels as $i=>$label){$label=trim((string)$label);$low=filter_var($from[$i]??null,FILTER_VALIDATE_FLOAT);$high=filter_var($to[$i]??null,FILTER_VALIDATE_FLOAT);$status=strtoupper((string)($statuses[$i]??''));if($label===''||$low===false||$high===false||$low<0||$high>100||$low>$high||!in_array($status,['PASS','FAIL'],true)){$errors[]='Every grade requires a label, a valid 0–100 range, and status.';continue;}$grades[]=['grade'=>$label,'from'=>(float)$low,'to'=>(float)$high,'status'=>$status];}usort($grades,fn($a,$b)=>$a['from']<=>$b['from']);for($i=1;$i<count($grades);$i++)if($grades[$i]['from']<=$grades[$i-1]['to'])$errors[]='Grading ranges must not overlap.';if(!$grades)$errors[]='At least one grade is required.';return [$grades,array_values(array_unique($errors))]; }
}
