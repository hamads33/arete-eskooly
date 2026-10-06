<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Core\{Request,Response};
final class PortalAnnouncementsController extends PortalController{public function index(Request$r):Response{$x=$this->identity();if(!$x)return$this->forbidden();$audience=$x['audience']==='employee'&&hasRole('teacher')?'teacher':$x['audience'];return$this->portalView('portal/announcements',$x['audience'],['pageTitle'=>'Announcements','activeMenu'=>'portal-announcements','instituteId'=>$x['instituteId'],'announcements'=>(new \App\Repositories\AnnouncementRepository($this->app->database()))->visible($x['instituteId'],$audience,$this->announcementClassIds($x['audience']))]);}}
