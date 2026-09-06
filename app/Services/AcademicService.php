<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\AcademicRepository;

final class AcademicService
{
    public function __construct(private readonly AcademicRepository $repo) {}
    public function createClass(int $instituteId,string $name,float $fee,?string $teacher): string {return $this->repo->createClass($instituteId,trim($name),$fee,$teacher)?'created':'duplicate';}
    public function assignSubject(int $instituteId,int $classId,string $name,float $marks): string {if(!$this->repo->classExists($classId,$instituteId))return 'invalid_class';return $this->repo->assign($instituteId,$classId,trim($name),$marks)?'assigned':'duplicate';}
}
