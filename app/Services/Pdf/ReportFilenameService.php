<?php
declare(strict_types=1);
namespace App\Services\Pdf;

final class ReportFilenameService
{
    public function make(string $type,array $segments=[]): string
    {
        $parts=[$type,...$segments];
        $safe=array_map(static function($part):string{$part=strtolower(trim((string)$part));$part=preg_replace('/[^a-z0-9]+/','-',$part)??'';return trim($part,'-')?:'document';},$parts);
        return implode('-',array_filter($safe)).'.pdf';
    }
}
