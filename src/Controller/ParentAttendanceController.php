<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response,Validator};
use App\Service\{ParentService,SchoolService};

final class ParentAttendanceController {
    public function summary(Request $r,array $p,int $student):never{
        ParentService::student($p,$student);$from=$r->query('from');$to=$r->query('to');
        $start=$from?Validator::date($from,'from'):(new \DateTimeImmutable('first day of this month'))->format('Y-m-d');$end=$to?Validator::date($to,'to'):(new \DateTimeImmutable())->format('Y-m-d');
        $st=Database::connection()->prepare("SELECT COUNT(*) total,SUM(CASE WHEN AttType='P' THEN 1 ELSE 0 END) present,SUM(CASE WHEN AttType='A' THEN 1 ELSE 0 END) absent,SUM(CASE WHEN AttType='H/2' THEN 1 ELSE 0 END) halfDay FROM AttItem WHERE StudentID=? AND CAST(Dated AS date) BETWEEN ? AND ?");$st->execute([$student,$start,$end]);$x=$st->fetch();$total=(int)($x['total']??0);$present=(int)($x['present']??0);$half=(int)($x['halfDay']??0);
        Response::success(['studentId'=>$student,'from'=>$start,'to'=>$end,'total'=>$total,'present'=>$present,'absent'=>(int)($x['absent']??0),'halfDay'=>$half,'percentage'=>$total?round(($present+$half*.5)*100/$total,2):0]);
    }
    public function history(Request $r,array $p,int $student):never{
        ParentService::student($p,$student);$from=$r->query('from');$to=$r->query('to');$start=$from?Validator::date($from,'from'):'1900-01-01';$end=$to?Validator::date($to,'to'):'2999-12-31';
        $st=Database::connection()->prepare("SELECT CONVERT(varchar(10),CAST(Dated AS date),23) date,AttType status,CAST(AttValue AS float) value FROM AttItem WHERE StudentID=? AND CAST(Dated AS date) BETWEEN ? AND ? ORDER BY Dated DESC");$st->execute([$student,$start,$end]);Response::success(['attendance'=>$st->fetchAll()]);
    }
    public function calendar(Request $r,array $p,int $student):never{
        ParentService::student($p,$student);$month=(string)$r->query('month',(new \DateTimeImmutable())->format('Y-m'));if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month))Response::error('month must be YYYY-MM.',422,'VALIDATION_ERROR');
        $start=$month.'-01';$end=(new \DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');$st=Database::connection()->prepare("SELECT CONVERT(varchar(10),CAST(Dated AS date),23) date,AttType status FROM AttItem WHERE StudentID=? AND CAST(Dated AS date) BETWEEN ? AND ? ORDER BY Dated");$st->execute([$student,$start,$end]);Response::success(['month'=>$month,'days'=>$st->fetchAll()]);
    }
}
