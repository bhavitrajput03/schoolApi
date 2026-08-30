<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\{Database,Response};
final class SchoolService {
    public static function currentSession(): array {
        $sql="SELECT TOP 1
                    OwnerSessionID id,
                    SessionName name,
                    CONVERT(varchar(10),CAST(StartDate AS date),23) startDate,
                    CONVERT(varchar(10),CAST(EndDate AS date),23) endDate,
                    SessionID sessionId
              FROM OwnerSession
              WHERE CAST(GETDATE() AS date)
                    BETWEEN CAST(StartDate AS date) AND CAST(EndDate AS date)
              ORDER BY StartDate DESC,OwnerSessionID DESC";
        $session=Database::connection()->query($sql)->fetch();
        if(!$session)Response::error('No academic session is configured for the current date.',503,'SESSION_NOT_CONFIGURED');
        return[
            'id'=>(int)$session['id'],
            'name'=>(string)$session['name'],
            'startDate'=>(string)$session['startDate'],
            'endDate'=>(string)$session['endDate'],
            'sessionId'=>$session['sessionId']===null?null:(int)$session['sessionId'],
        ];
    }
    public static function currentSessionId(): int {
        return self::currentSession()['id'];
    }
    public static function assertAssignment(string $userId,int $classId,int $sectionId,?int $subjectId=null): void {
        $sql='SELECT COUNT(*) FROM ApiTeacherAssignment WHERE ApiUserID=? AND ClassID=? AND SectionID=? AND OwnerSessionID=? AND IsActive=1';
        $p=[$userId,$classId,$sectionId,self::currentSessionId()];if($subjectId!==null){$sql.=' AND SubjectID=?';$p[]=$subjectId;}
        $st=Database::connection()->prepare($sql);$st->execute($p);if(!(int)$st->fetchColumn())Response::error('You are not assigned to this class/section/subject.',403,'FORBIDDEN');
    }
    public static function subSubjectId(int $subjectId): int {
        $st=Database::connection()->prepare('SELECT TOP 1 CBSEExamSubSubjectID FROM CBSEExamSubSubject WHERE CBSEExamSubjectID=? ORDER BY CBSEExamSubSubjectID');$st->execute([$subjectId]);$id=$st->fetchColumn();
        if(!$id)Response::error('Subject mapping not found.',422,'SUBJECT_MAPPING_NOT_FOUND');return(int)$id;
    }
}
