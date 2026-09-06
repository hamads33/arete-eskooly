<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\{DashboardRepository,SettingsRepository};

final class DashboardController extends AdminPageController
{
    public function index(Request $request): Response
    {
        $institute=(new SettingsRepository($this->app->database()))->institute();
        $data=(new DashboardRepository($this->app->database()))->summary((int)$institute['id']);
        $students=[]; foreach($data['admissions'] as $row){$name=(string)$row['student_name'];$students[]=['name'=>$name,'registration'=>$row['registration_no'],'className'=>$row['class_name'],'initials'=>strtoupper(substr(preg_replace('/[^A-Za-z ]/','',$name),0,1).substr(strrchr(' '.$name,' '),1,1))];}
        return $this->renderPage('dashboard',['dashboardData'=>['totalStudents'=>$data['students'],'totalEmployees'=>$data['employees'],'revenue'=>$data['revenue'],'profit'=>$data['revenue']-$data['costs'],'months'=>$data['months'],'income'=>$data['incomes'],'expense'=>$data['expenses'],'feeCollected'=>$data['fee']['total']>0?round(((float)$data['fee']['paid']/(float)$data['fee']['total'])*100):0,'classLabels'=>array_column($data['classes'],'name'),'classStrength'=>array_map('intval',array_column($data['classes'],'strength')),'students'=>$students],'dashboardSummary'=>$data,'institute'=>$institute]);
    }
}
