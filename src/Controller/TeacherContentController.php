<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\{Database,Request,Response,Validator};
use App\Service\{SchoolService};

final class TeacherContentController {
    public function createNotice(Request $request,array $user):never{
        $data=$request->json();
        Validator::required($data,['noticeType','targetType','title','body']);
        $noticeType=strtolower(trim((string)$data['noticeType']));
        $targetType=strtolower(trim((string)$data['targetType']));
        if(!in_array($noticeType,['general','academic','exam','event','holiday','fee','urgent'],true))
            Response::error('Invalid noticeType.',422,'VALIDATION_ERROR',['allowed'=>['general','academic','exam','event','holiday','fee','urgent']]);
        if(!in_array($targetType,['school','class','section','student'],true))
            Response::error('Invalid targetType.',422,'VALIDATION_ERROR',['allowed'=>['school','class','section','student']]);

        $session=SchoolService::currentSessionId();$class=null;$section=null;$student=null;
        if($targetType==='school'){
            if(strtolower((string)$user['Role'])!=='admin')Response::error('Only an admin can publish a school-wide notice.',403,'FORBIDDEN');
        }else{
            $class=Validator::id($data['classId']??null,'classId');
            if($targetType==='class')self::assertClass($user['ApiUserID'],$class,$session);
            else{
                $section=Validator::id($data['sectionId']??null,'sectionId');
                SchoolService::assertAssignment($user['ApiUserID'],$class,$section);
                if($targetType==='student'){
                    $student=Validator::id($data['studentId']??null,'studentId');
                    self::assertStudent($student,$class,$section,$session);
                }
            }
        }

        $title=trim((string)$data['title']);$body=trim((string)$data['body']);
        if($title===''||mb_strlen($title)>200||$body==='')Response::error('title and body are invalid.',422,'VALIDATION_ERROR');
        $attachment=self::url($data['attachmentUrl']??null);
        $db=Database::connection();$sql='INSERT INTO AppNotice(OwnerSessionID,NoticeType,TargetType,Title,Body,ClassID,SectionID,StudentID,PublishedAt,PublishedBy,CreatedByApiUserID,AttachmentUrl) OUTPUT INSERTED.AppNoticeID VALUES(?,?,?,?,?,?,?,?,SYSUTCDATETIME(),?,?,?)';
        $st=$db->prepare($sql);$st->execute([$session,$noticeType,$targetType,$title,$body,$class,$section,$student,(string)$user['DisplayName'],(string)$user['ApiUserID'],$attachment]);$id=(int)$st->fetchColumn();
        Response::success(['notice'=>self::notice($db,$id)],201);
    }

    public function createHomework(Request $request,array $user):never{
        $data=$request->json();Validator::required($data,['classId','sectionId','subjectId','title','description','homeworkDate']);
        $class=Validator::id($data['classId'],'classId');$section=Validator::id($data['sectionId'],'sectionId');$subject=Validator::id($data['subjectId'],'subjectId');
        SchoolService::assertAssignment($user['ApiUserID'],$class,$section,$subject);
        $homeworkDate=Validator::date($data['homeworkDate'],'homeworkDate');$dueDate=null;
        if(isset($data['dueDate'])&&$data['dueDate']!==''){$dueDate=Validator::date($data['dueDate'],'dueDate');if($dueDate<$homeworkDate)Response::error('dueDate cannot be before homeworkDate.',422,'VALIDATION_ERROR');}
        $title=trim((string)$data['title']);$description=trim((string)$data['description']);
        if($title===''||mb_strlen($title)>200||$description==='')Response::error('title and description are invalid.',422,'VALIDATION_ERROR');
        $db=Database::connection();$sql='INSERT INTO AppHomework(OwnerSessionID,ClassID,SectionID,SubjectID,Title,Description,HomeworkDate,DueDate,AttachmentUrl,TeacherEmployeeID,CreatedByApiUserID) OUTPUT INSERTED.AppHomeworkID VALUES(?,?,?,?,?,?,?,?,?,?,?)';
        $st=$db->prepare($sql);$st->execute([SchoolService::currentSessionId(),$class,$section,$subject,$title,$description,$homeworkDate,$dueDate,self::url($data['attachmentUrl']??null),$user['EmployeeID'],(string)$user['ApiUserID']]);$id=(int)$st->fetchColumn();
        Response::success(['homework'=>self::homework($db,$id)],201);
    }

    private static function assertClass(string|int $userId,int $class,int $session):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM ApiTeacherAssignment WHERE ApiUserID=? AND OwnerSessionID=? AND ClassID=? AND IsActive=1');$st->execute([$userId,$session,$class]);if(!(int)$st->fetchColumn())Response::error('You are not assigned to this class.',403,'FORBIDDEN');}
    private static function assertStudent(int $student,int $class,int $section,int $session):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND IsLeave=0');$st->execute([$student,$session,$class,$section]);if(!(int)$st->fetchColumn())Response::error('Student is not in the selected class and section.',422,'STUDENT_NOT_IN_CLASS');}
    private static function url(mixed $value):?string{if($value===null||trim((string)$value)==='')return null;$url=trim((string)$value);if(mb_strlen($url)>1000)Response::error('attachmentUrl is too long.',422,'VALIDATION_ERROR');return$url;}
    private static function notice(\PDO $db,int $id):array{$st=$db->prepare('SELECT AppNoticeID id,NoticeType noticeType,TargetType targetType,Title title,Body body,ClassID classId,SectionID sectionId,StudentID studentId,PublishedAt publishedAt,PublishedBy publishedBy,AttachmentUrl attachmentUrl FROM AppNotice WHERE AppNoticeID=?');$st->execute([$id]);return$st->fetch();}
    private static function homework(\PDO $db,int $id):array{$st=$db->prepare('SELECT AppHomeworkID id,ClassID classId,SectionID sectionId,SubjectID subjectId,Title title,Description description,HomeworkDate homeworkDate,DueDate dueDate,AttachmentUrl attachmentUrl,CreatedAt createdAt FROM AppHomework WHERE AppHomeworkID=?');$st->execute([$id]);return$st->fetch();}
}
