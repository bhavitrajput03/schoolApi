<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response,Validator};
use App\Service\{DeviceService,SchoolService};
final class TeacherController {
    public function dashboard(Request $r,array $u):never{$session=SchoolService::currentSession();$st=Database::connection()->prepare("SELECT COUNT(DISTINCT CONCAT(ClassID,':',SectionID)) FROM ApiTeacherAssignment WHERE ApiUserID=? AND OwnerSessionID=? AND IsActive=1");$st->execute([$u['ApiUserID'],$session['id']]);$id=self::identifier($u['ApiUserID']);Response::success(['teacher'=>['id'=>$id,'teacherId'=>$id,'apiUserId'=>$id,'employeeId'=>$u['EmployeeID']===null?null:(int)$u['EmployeeID'],'name'=>$u['DisplayName'],'role'=>$u['Role']],'academicSessionId'=>$session['id'],'academicSession'=>$session,'assignedClassSections'=>(int)$st->fetchColumn()]);}
    public function classes(Request $r,array $u):never{$st=Database::connection()->prepare("SELECT DISTINCT c.ClassmasterID id,c.Classmaster name,c.SNo sortOrder FROM ApiTeacherAssignment a JOIN ClassMaster c ON c.ClassmasterID=a.ClassID WHERE a.ApiUserID=? AND a.OwnerSessionID=? AND a.IsActive=1 ORDER BY c.SNo,c.Classmaster");$st->execute([$u['ApiUserID'],SchoolService::currentSessionId()]);Response::success($st->fetchAll());}
    public function sections(Request $r,array $u,int $class):never{$st=Database::connection()->prepare("SELECT DISTINCT s.SectionMasterID id,s.SectionName name FROM ApiTeacherAssignment a JOIN SectionMaster s ON s.SectionMasterID=a.SectionID WHERE a.ApiUserID=? AND a.OwnerSessionID=? AND a.ClassID=? AND a.IsActive=1 ORDER BY s.SectionName");$st->execute([$u['ApiUserID'],SchoolService::currentSessionId(),$class]);Response::success($st->fetchAll());}
    public function subjects(Request $r,array $u,int $class,int $section):never{$st=Database::connection()->prepare("SELECT DISTINCT s.CBSEExamSubjectID id,s.CBSEExamSubject name,s.SubjectAbbreviation abbreviation FROM ApiTeacherAssignment a JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=a.SubjectID WHERE a.ApiUserID=? AND a.OwnerSessionID=? AND a.ClassID=? AND a.SectionID=? AND a.IsActive=1 ORDER BY s.CBSEExamSubject");$st->execute([$u['ApiUserID'],SchoolService::currentSessionId(),$class,$section]);Response::success($st->fetchAll());}
    public function students(Request $r,array $u,int $class,int $section):never{
        SchoolService::assertAssignment($u['ApiUserID'],$class,$section);$session=SchoolService::currentSessionId();
        $sql="SELECT s.StudentID studentId,s.StudentName name,s.AdmissionNo admissionNo,ss.RollNo rollNumber,s.FatherName fatherName
              FROM StudentSession ss JOIN Student s ON s.StudentID=ss.StudentID
              WHERE ss.OwnerSessionID=? AND ss.ClassID=? AND ss.SectionID=? AND ss.IsLeave=0
              ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9 AND ss.RollNo NOT LIKE '%[^0-9]%'
                            THEN CONVERT(int,ss.RollNo) ELSE 2147483647 END,ss.RollNo,s.StudentName";
        $st=Database::connection()->prepare($sql);$st->execute([$session,$class,$section]);Response::success(['students'=>$st->fetchAll()]);
    }
    public function registerDevice(Request $r,array $u):never{
        $d=$r->json();Validator::required($d,['deviceId','fcmToken','platform']);if(!in_array(strtolower((string)$d['platform']),['android','ios','web'],true))Response::error('platform must be android, ios or web.',422,'VALIDATION_ERROR');
        DeviceService::teacher($d,$u['ApiUserID']);Response::success(['deviceId'=>(string)$d['deviceId'],'registered'=>true]);
    }
    private static function identifier(mixed $value):string|int{return is_int($value)||is_string($value)&&ctype_digit($value)?(int)$value:(string)$value;}
}
