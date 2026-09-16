<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\{Database,Response};

final class ParentService {
    public static function student(array $parent,int $studentId):array{
        $session=SchoolService::currentSessionId();
        $sql='SELECT TOP 1 ss.ClassID,ss.SectionID,ss.OwnerSessionID
              FROM AppParentStudent link JOIN StudentSession ss ON ss.StudentID=link.StudentID
              WHERE link.AppParentID=? AND link.StudentID=? AND ss.OwnerSessionID=? AND ss.IsLeave=0';
        $st=Database::connection()->prepare($sql);$st->execute([$parent['AppParentID'],$studentId,$session]);$row=$st->fetch();
        if(!$row)Response::error('Student is not linked with this parent.',403,'STUDENT_FORBIDDEN');return$row;
    }
    public static function bool(mixed $value):int{return filter_var($value,FILTER_VALIDATE_BOOL)?1:0;}
    public static function page(mixed $value,int $default=1):int{$n=filter_var($value,FILTER_VALIDATE_INT);return$n&&$n>0?min($n,100000):$default;}
    public static function limit(mixed $value,int $default=25):int{$n=filter_var($value,FILTER_VALIDATE_INT);return$n&&$n>0?min($n,100):$default;}
}
