<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response,Validator};
use App\Service\SchoolService;
final class MarksController {
    public function subjectStudents(Request $r,array $u):never{
        [$class,$section,$term,$subject]=$this->context($r);SchoolService::assertAssignment($u['ApiUserID'],$class,$section,$subject);$session=SchoolService::currentSessionId();$sub=SchoolService::subSubjectId($subject);
        $sql="SELECT s.StudentID id,s.StudentName name,s.AdmissionNo admissionNo,ss.RollNo rollNo,s.FatherName fatherName,m.MaxMarks maxMarks,m.ObtainMarks obtainedMarks,m.IsAbsent isAbsent,m.IsMedical isMedical
              FROM StudentSession ss JOIN Student s ON s.StudentID=ss.StudentID LEFT JOIN CBSEExamMarksEntry m ON m.StudentID=s.StudentID AND m.OwnerSessionID=ss.OwnerSessionID AND m.TermOptionID=? AND m.SubSubjectID=?
              WHERE ss.OwnerSessionID=? AND ss.ClassID=? AND ss.SectionID=? AND ss.IsLeave=0
              ORDER BY CASE WHEN ss.RollNo<>'' AND LEN(ss.RollNo)<=9 AND ss.RollNo NOT LIKE '%[^0-9]%'
                            THEN CONVERT(int,ss.RollNo) ELSE 2147483647 END,ss.RollNo,s.StudentName";
        $st=Database::connection()->prepare($sql);$st->execute([$term,$sub,$session,$class,$section]);Response::success($st->fetchAll());
    }
    public function saveSubjectWise(Request $r,array $u):never{
        $d=$r->json();Validator::required($d,['classId','sectionId','termOptionId','subjectId','maxMarks','marks']);$class=Validator::id($d['classId'],'classId');$section=Validator::id($d['sectionId'],'sectionId');$term=Validator::id($d['termOptionId'],'termOptionId');$subject=Validator::id($d['subjectId'],'subjectId');
        SchoolService::assertAssignment($u['ApiUserID'],$class,$section,$subject);$this->write($u,$class,$section,$term,(float)$d['maxMarks'],$d['marks'],static fn(array $x):int=>$subject);
    }
    public function studentWise(Request $r,array $u,int $student):never{
        $term=Validator::id($r->query('termOptionId'),'termOptionId');$session=SchoolService::currentSessionId();$db=Database::connection();$st=$db->prepare('SELECT TOP 1 ClassID,SectionID FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND IsLeave=0');$st->execute([$student,$session]);$ctx=$st->fetch();
        if(!$ctx)Response::error('Student not found in current session.',404,'STUDENT_NOT_FOUND');SchoolService::assertAssignment($u['ApiUserID'],(int)$ctx['ClassID'],(int)$ctx['SectionID']);
        $sql="SELECT s.CBSEExamSubjectID subjectId,s.CBSEExamSubject subjectName,m.MaxMarks maxMarks,m.ObtainMarks obtainedMarks,m.IsAbsent isAbsent,m.IsMedical isMedical
              FROM ApiTeacherAssignment a JOIN CBSEExamSubject s ON s.CBSEExamSubjectID=a.SubjectID JOIN CBSEExamSubSubject ss ON ss.CBSEExamSubjectID=s.CBSEExamSubjectID
              LEFT JOIN CBSEExamMarksEntry m ON m.SubSubjectID=ss.CBSEExamSubSubjectID AND m.StudentID=? AND m.TermOptionID=? AND m.OwnerSessionID=?
              WHERE a.ApiUserID=? AND a.ClassID=? AND a.SectionID=? AND a.OwnerSessionID=? AND a.IsActive=1 ORDER BY s.CBSEExamSubject";
        $st=$db->prepare($sql);$st->execute([$student,$term,$session,$u['ApiUserID'],$ctx['ClassID'],$ctx['SectionID'],$session]);Response::success(['studentId'=>$student,'classId'=>$ctx['ClassID'],'sectionId'=>$ctx['SectionID'],'marks'=>$st->fetchAll()]);
    }
    public function saveStudentWise(Request $r,array $u):never{
        $d=$r->json();Validator::required($d,['studentId','termOptionId','marks']);$student=Validator::id($d['studentId'],'studentId');$term=Validator::id($d['termOptionId'],'termOptionId');$session=SchoolService::currentSessionId();$db=Database::connection();$st=$db->prepare('SELECT TOP 1 ClassID,SectionID FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND IsLeave=0');$st->execute([$student,$session]);$ctx=$st->fetch();if(!$ctx)Response::error('Student not found.',404,'STUDENT_NOT_FOUND');
        $rows=[];foreach($d['marks'] as $m){$m['studentId']=$student;$rows[]=$m;}$this->write($u,(int)$ctx['ClassID'],(int)$ctx['SectionID'],$term,0,$rows,static fn(array $x):int=>Validator::id($x['subjectId']??null,'subjectId'));
    }
    private function write(array $u,int $class,int $section,int $term,float $commonMax,array $rows,callable $subjectOf):never{
        if(!$rows)Response::error('marks must be a non-empty array.',422,'VALIDATION_ERROR');$session=SchoolService::currentSessionId();$db=Database::connection();$db->beginTransaction();$saved=0;
        try{foreach($rows as $x){Validator::required($x,['studentId']);$student=Validator::id($x['studentId'],'studentId');$subject=$subjectOf($x);SchoolService::assertAssignment($u['ApiUserID'],$class,$section,$subject);$sub=SchoolService::subSubjectId($subject);$max=(float)($x['maxMarks']??$commonMax);$abs=(bool)($x['isAbsent']??false);$med=(bool)($x['isMedical']??false);
            if($max<=0||($abs&&$med))Response::error('Invalid maxMarks/absence flags.',422,'VALIDATION_ERROR');$obt=($abs||$med)?null:(isset($x['obtainedMarks'])?(float)$x['obtainedMarks']:null);if(!$abs&&!$med&&($obt===null||$obt<0||$obt>$max))Response::error('obtainedMarks must be between 0 and maxMarks.',422,'VALIDATION_ERROR');
            $check=$db->prepare('SELECT COUNT(*) FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND IsLeave=0');$check->execute([$student,$session,$class,$section]);if(!(int)$check->fetchColumn())Response::error('Student does not belong to selected class/section.',422,'INVALID_STUDENT');
            $pct=$obt===null?null:round($obt*100/$max,2);$up=$db->prepare('UPDATE CBSEExamMarksEntry SET MaxMarks=?,ObtainMarks=?,Percentage=?,IsAbsent=?,IsMedical=? WHERE StudentID=? AND OwnerSessionID=? AND TermOptionID=? AND SubSubjectID=?');$up->execute([$max,$obt,$pct,(int)$abs,(int)$med,$student,$session,$term,$sub]);
            if($up->rowCount()===0)$db->prepare('INSERT INTO CBSEExamMarksEntry(TermOptionID,SubSubjectID,StudentID,MaxMarks,MinMarks,ObtainMarks,Percentage,IsAbsent,IsMedical,OwnerSessionID,IsDef) VALUES(?,?,?,?,0,?,?,?,?,?,0)')->execute([$term,$sub,$student,$max,$obt,$pct,(int)$abs,(int)$med,$session]);$saved++;
        }$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}Response::success(['saved'=>$saved]);
    }
    private function context(Request $r):array{return[Validator::id($r->query('classId'),'classId'),Validator::id($r->query('sectionId'),'sectionId'),Validator::id($r->query('termOptionId'),'termOptionId'),Validator::id($r->query('subjectId'),'subjectId')];}
}
