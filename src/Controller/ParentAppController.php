<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Env,Request,Response};
use App\Service\{LegacyMapper,ParentService,SchoolService};

final class ParentAppController {
    public function school(Request $r,array $p):never{
        $st=Database::connection()->prepare('SELECT TOP 1 * FROM SchoolBranch WHERE SchoolBranchID=?');$st->execute([$p['SchoolBranchID']]);$s=$st->fetch();if(!$s)Response::error('School not found.',404,'SCHOOL_NOT_FOUND');
        Response::success(['school'=>['id'=>(int)$p['SchoolBranchID'],'name'=>LegacyMapper::value($s,['BranchName','SchoolName','Name']),'code'=>LegacyMapper::value($s,['Prefix','SchoolCode']),'address'=>LegacyMapper::value($s,['Address','BranchAddress']),'phone'=>LegacyMapper::value($s,['Phone','Mobile','ContactNo']),'alternatePhone'=>LegacyMapper::value($s,['AlternatePhone','Phone2']),'email'=>LegacyMapper::value($s,['Email','EmailID']),'website'=>LegacyMapper::value($s,['Website','WebSite']),'principal'=>LegacyMapper::value($s,['Principal','PrincipalName']),'affiliationNo'=>LegacyMapper::value($s,['AffiliationNo']),'udiseCode'=>LegacyMapper::value($s,['UDISECode','UdiseNo'])]]);
    }
    public function session(Request $r,array $p):never{Response::success(['academicSession'=>SchoolService::currentSession()]);}
    public function config(Request $r,?array $p=null):never{Response::success(['appName'=>'ET Parent','minimumVersion'=>Env::get('APP_MINIMUM_VERSION','1.0.0'),'latestVersion'=>Env::get('APP_LATEST_VERSION','1.0.0'),'forceUpdate'=>false,'maintenance'=>false,'paymentEnabled'=>Env::get('PAYMENT_PROVIDER')!==null]);}
    public function settings(Request $r,array $p):never{
        $db=Database::connection();$db->prepare('IF NOT EXISTS(SELECT 1 FROM AppParentSetting WHERE AppParentID=?) INSERT INTO AppParentSetting(AppParentID) VALUES(?)')->execute([$p['AppParentID'],$p['AppParentID']]);$st=$db->prepare('SELECT AttendanceNotifications attendanceNotifications,FeeNotifications feeNotifications,HomeworkNotifications homeworkNotifications,NoticeNotifications noticeNotifications,Language language,UpdatedAt updatedAt FROM AppParentSetting WHERE AppParentID=?');$st->execute([$p['AppParentID']]);Response::success(['settings'=>$st->fetch()]);
    }
    public function updateSettings(Request $r,array $p):never{
        $d=$r->json();$db=Database::connection();$db->prepare('IF NOT EXISTS(SELECT 1 FROM AppParentSetting WHERE AppParentID=?) INSERT INTO AppParentSetting(AppParentID) VALUES(?)')->execute([$p['AppParentID'],$p['AppParentID']]);
        $current=$db->prepare('SELECT * FROM AppParentSetting WHERE AppParentID=?');$current->execute([$p['AppParentID']]);$x=$current->fetch();$language=substr((string)($d['language']??$x['Language']),0,10);
        $db->prepare('UPDATE AppParentSetting SET AttendanceNotifications=?,FeeNotifications=?,HomeworkNotifications=?,NoticeNotifications=?,Language=?,UpdatedAt=SYSUTCDATETIME() WHERE AppParentID=?')->execute([isset($d['attendanceNotifications'])?ParentService::bool($d['attendanceNotifications']):$x['AttendanceNotifications'],isset($d['feeNotifications'])?ParentService::bool($d['feeNotifications']):$x['FeeNotifications'],isset($d['homeworkNotifications'])?ParentService::bool($d['homeworkNotifications']):$x['HomeworkNotifications'],isset($d['noticeNotifications'])?ParentService::bool($d['noticeNotifications']):$x['NoticeNotifications'],$language,$p['AppParentID']]);$this->settings($r,$p);
    }
}
