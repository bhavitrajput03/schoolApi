<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\{Database,Request,Response,Validator};
use App\Service\{FcmService,SchoolService};

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
        $db=Database::connection();$db->beginTransaction();
        try{
            $sql='INSERT INTO AppNotice(OwnerSessionID,NoticeType,TargetType,Title,Body,ClassID,SectionID,StudentID,PublishedAt,PublishedBy,CreatedByApiUserID,AttachmentUrl) OUTPUT INSERTED.AppNoticeID VALUES(?,?,?,?,?,?,?,?,SYSUTCDATETIME(),?,?,?)';
            $st=$db->prepare($sql);$st->execute([$session,$noticeType,$targetType,$title,$body,$class,$section,$student,(string)$user['DisplayName'],(string)$user['ApiUserID'],$attachment]);$id=(int)$st->fetchColumn();
            $notificationCount=self::createNotifications($db,$id,$session,$targetType,$class,$section,$student,$title,$body);
            $notice=self::notice($db,$id);$tokens=self::notificationTokens($db,'notice',$id);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        $push=FcmService::notice($tokens,$id,$title,$body);
        Response::success(['notice'=>$notice,'notification'=>['inAppCreated'=>$notificationCount,'push'=>$push]],201);
    }

    public function createHomework(Request $request,array $user):never{
        $data=$request->json();Validator::required($data,['classId','sectionId','subjectId','title','description','homeworkDate']);
        $class=Validator::id($data['classId'],'classId');$section=Validator::id($data['sectionId'],'sectionId');$subject=Validator::id($data['subjectId'],'subjectId');
        SchoolService::assertAssignment($user['ApiUserID'],$class,$section,$subject);
        $homeworkDate=Validator::date($data['homeworkDate'],'homeworkDate');$dueDate=null;
        if(isset($data['dueDate'])&&$data['dueDate']!==''){$dueDate=Validator::date($data['dueDate'],'dueDate');if($dueDate<$homeworkDate)Response::error('dueDate cannot be before homeworkDate.',422,'VALIDATION_ERROR');}
        $title=trim((string)$data['title']);$description=trim((string)$data['description']);
        if($title===''||mb_strlen($title)>200||$description==='')Response::error('title and description are invalid.',422,'VALIDATION_ERROR');
        $session=SchoolService::currentSessionId();$db=Database::connection();$db->beginTransaction();
        try{
            $sql='INSERT INTO AppHomework(OwnerSessionID,ClassID,SectionID,SubjectID,Title,Description,HomeworkDate,DueDate,AttachmentUrl,TeacherEmployeeID,CreatedByApiUserID) OUTPUT INSERTED.AppHomeworkID VALUES(?,?,?,?,?,?,?,?,?,?,?)';
            $st=$db->prepare($sql);$st->execute([$session,$class,$section,$subject,$title,$description,$homeworkDate,$dueDate,self::url($data['attachmentUrl']??null),$user['EmployeeID'],(string)$user['ApiUserID']]);$id=(int)$st->fetchColumn();
            $notificationCount=self::createHomeworkNotifications($db,$id,$session,$class,$section,$title,$description);
            $homework=self::homework($db,$id);$tokens=self::notificationTokens($db,'homework',$id);$db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        $push=FcmService::homework($tokens,$id,'New Homework: '.$title,$description);
        Response::success(['homework'=>$homework,'notification'=>['inAppCreated'=>$notificationCount,'push'=>$push]],201);
    }

    public function myHomework(Request $request,array $user):never{
        $session=SchoolService::currentSessionId();$where=['h.OwnerSessionID=?','h.CreatedByApiUserID=?','h.IsActive=1'];$args=[$session,(string)$user['ApiUserID']];
        foreach(['classId'=>'h.ClassID','sectionId'=>'h.SectionID','subjectId'=>'h.SubjectID'] as $query=>$column){$value=$request->query($query);if($value!==null&&$value!==''){$where[]="{$column}=?";$args[]=Validator::id($value,$query);}}
        $sql='SELECT h.AppHomeworkID id,h.ClassID classId,c.Classmaster className,h.SectionID sectionId,s.SectionName sectionName,h.SubjectID subjectId,sub.CBSEExamSubject subjectName,h.Title title,h.Description description,h.HomeworkDate homeworkDate,h.DueDate dueDate,h.AttachmentUrl attachmentUrl,h.CreatedAt createdAt FROM AppHomework h JOIN ClassMaster c ON c.ClassmasterID=h.ClassID LEFT JOIN SectionMaster s ON s.SectionMasterID=h.SectionID LEFT JOIN CBSEExamSubject sub ON sub.CBSEExamSubjectID=h.SubjectID WHERE '.implode(' AND ',$where).' ORDER BY h.HomeworkDate DESC,h.AppHomeworkID DESC';
        $st=Database::connection()->prepare($sql);$st->execute($args);Response::success(['homework'=>$st->fetchAll()]);
    }

    public function studentHomework(Request $request,array $user,int $student):never{
        $session=SchoolService::currentSessionId();$db=Database::connection();$ctx=$db->prepare('SELECT TOP 1 ClassID,SectionID FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND IsLeave=0');$ctx->execute([$student,$session]);$row=$ctx->fetch();if(!$row)Response::error('Student is not active in the current session.',404,'STUDENT_NOT_FOUND');
        SchoolService::assertAssignment($user['ApiUserID'],(int)$row['ClassID'],(int)$row['SectionID']);
        $sql='SELECT h.AppHomeworkID id,h.ClassID classId,h.SectionID sectionId,h.SubjectID subjectId,sub.CBSEExamSubject subjectName,h.Title title,h.Description description,h.HomeworkDate homeworkDate,h.DueDate dueDate,h.AttachmentUrl attachmentUrl,h.CreatedAt createdAt FROM AppHomework h LEFT JOIN CBSEExamSubject sub ON sub.CBSEExamSubjectID=h.SubjectID WHERE h.OwnerSessionID=? AND h.ClassID=? AND (h.SectionID IS NULL OR h.SectionID=?) AND h.CreatedByApiUserID=? AND h.IsActive=1 ORDER BY h.HomeworkDate DESC,h.AppHomeworkID DESC';
        $st=$db->prepare($sql);$st->execute([$session,$row['ClassID'],$row['SectionID'],(string)$user['ApiUserID']]);Response::success(['studentId'=>$student,'homework'=>$st->fetchAll()]);
    }

    private static function assertClass(string|int $userId,int $class,int $session):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM ApiTeacherAssignment WHERE ApiUserID=? AND OwnerSessionID=? AND ClassID=? AND IsActive=1');$st->execute([$userId,$session,$class]);if(!(int)$st->fetchColumn())Response::error('You are not assigned to this class.',403,'FORBIDDEN');}
    private static function assertStudent(int $student,int $class,int $section,int $session):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM StudentSession WHERE StudentID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND IsLeave=0');$st->execute([$student,$session,$class,$section]);if(!(int)$st->fetchColumn())Response::error('Student is not in the selected class and section.',422,'STUDENT_NOT_IN_CLASS');}
    private static function url(mixed $value):?string{if($value===null||trim((string)$value)==='')return null;$url=trim((string)$value);if(mb_strlen($url)>1000)Response::error('attachmentUrl is too long.',422,'VALIDATION_ERROR');return$url;}
    private static function createNotifications(\PDO $db,int $notice,int $session,string $target,?int $class,?int $section,?int $student,string $title,string $body):int{
        $where='ss.OwnerSessionID=? AND ss.IsLeave=0';$args=[$session];
        if($target==='class'){$where.=' AND ss.ClassID=?';$args[]=$class;}
        elseif($target==='section'){$where.=' AND ss.ClassID=? AND ss.SectionID=?';$args[]=$class;$args[]=$section;}
        elseif($target==='student'){$where.=' AND ss.StudentID=?';$args[]=$student;}
        $sql="INSERT INTO AppNotification(StudentID,NotificationType,Title,Body,RelatedType,RelatedID)
              SELECT DISTINCT ss.StudentID,'notice',?,?, 'notice',?
              FROM StudentSession ss WHERE {$where}";
        $st=$db->prepare($sql);$st->execute(array_merge([$title,mb_substr($body,0,1000),(string)$notice],$args));return$st->rowCount();
    }
    private static function createHomeworkNotifications(\PDO $db,int $homework,int $session,int $class,int $section,string $title,string $body):int{
        $sql="INSERT INTO AppNotification(StudentID,NotificationType,Title,Body,RelatedType,RelatedID)
              SELECT DISTINCT ss.StudentID,'homework',?,?, 'homework',?
              FROM StudentSession ss WHERE ss.OwnerSessionID=? AND ss.ClassID=? AND ss.SectionID=? AND ss.IsLeave=0";
        $st=$db->prepare($sql);$st->execute(['New Homework: '.$title,mb_substr($body,0,1000),(string)$homework,$session,$class,$section]);return$st->rowCount();
    }
    private static function notificationTokens(\PDO $db,string $type,int $relatedId):array{
        $sql="SELECT DISTINCT d.FcmToken FROM AppNotification n
              JOIN AppParentStudent link ON link.StudentID=n.StudentID
              JOIN AppParentDevice d ON d.AppParentID=link.AppParentID AND d.IsActive=1
              WHERE n.RelatedType=? AND n.RelatedID=? AND d.FcmToken<>''";
        $st=$db->prepare($sql);$st->execute([$type,(string)$relatedId]);return$st->fetchAll(\PDO::FETCH_COLUMN);
    }
    private static function notice(\PDO $db,int $id):array{$st=$db->prepare('SELECT AppNoticeID id,NoticeType noticeType,TargetType targetType,Title title,Body body,ClassID classId,SectionID sectionId,StudentID studentId,PublishedAt publishedAt,PublishedBy publishedBy,AttachmentUrl attachmentUrl FROM AppNotice WHERE AppNoticeID=?');$st->execute([$id]);return$st->fetch();}
    private static function homework(\PDO $db,int $id):array{$st=$db->prepare('SELECT AppHomeworkID id,ClassID classId,SectionID sectionId,SubjectID subjectId,Title title,Description description,HomeworkDate homeworkDate,DueDate dueDate,AttachmentUrl attachmentUrl,CreatedAt createdAt FROM AppHomework WHERE AppHomeworkID=?');$st->execute([$id]);return$st->fetch();}
}
