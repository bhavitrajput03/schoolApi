<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\{ParentService,SchoolService};

final class NoticeController {
    public function list(Request $r,array $p,int $student):never{
        $ctx=ParentService::student($p,$student);$sql="SELECT n.AppNoticeID id,n.NoticeType noticeType,n.TargetType targetType,n.Title title,n.Body body,n.AttachmentUrl attachmentUrl,n.PublishedAt publishedAt,n.PublishedBy publishedBy,CAST(CASE WHEN rd.AppNoticeID IS NULL THEN 0 ELSE 1 END AS bit) isRead
          FROM AppNotice n LEFT JOIN AppNoticeRead rd ON rd.AppNoticeID=n.AppNoticeID AND rd.AppParentID=?
          WHERE n.IsActive=1 AND (n.OwnerSessionID IS NULL OR n.OwnerSessionID=?) AND (n.ClassID IS NULL OR n.ClassID=?) AND (n.SectionID IS NULL OR n.SectionID=?) AND (n.StudentID IS NULL OR n.StudentID=?) ORDER BY n.PublishedAt DESC";
        $st=Database::connection()->prepare($sql);$st->execute([$p['AppParentID'],$ctx['OwnerSessionID'],$ctx['ClassID'],$ctx['SectionID'],$student]);Response::success(['notices'=>$st->fetchAll()]);
    }
    public function detail(Request $r,array $p,int $notice):never{
        $st=Database::connection()->prepare('SELECT TOP 1 AppNoticeID id,NoticeType noticeType,TargetType targetType,Title title,Body body,AttachmentUrl attachmentUrl,PublishedAt publishedAt,PublishedBy publishedBy FROM AppNotice n WHERE n.AppNoticeID=? AND n.IsActive=1 AND EXISTS(SELECT 1 FROM AppParentStudent l JOIN StudentSession ss ON ss.StudentID=l.StudentID WHERE l.AppParentID=? AND (n.OwnerSessionID IS NULL OR n.OwnerSessionID=ss.OwnerSessionID) AND (n.ClassID IS NULL OR n.ClassID=ss.ClassID) AND (n.SectionID IS NULL OR n.SectionID=ss.SectionID) AND (n.StudentID IS NULL OR n.StudentID=ss.StudentID) AND ss.IsLeave=0)');$st->execute([$notice,$p['AppParentID']]);$row=$st->fetch();if(!$row)Response::error('Notice not found.',404,'NOTICE_NOT_FOUND');Response::success(['notice'=>$row]);
    }
    public function read(Request $r,array $p,int $notice):never{
        $this->detailCheck($p,$notice);Database::connection()->prepare('IF NOT EXISTS(SELECT 1 FROM AppNoticeRead WHERE AppParentID=? AND AppNoticeID=?) INSERT INTO AppNoticeRead(AppParentID,AppNoticeID) VALUES(?,?)')->execute([$p['AppParentID'],$notice,$p['AppParentID'],$notice]);Response::success(['noticeId'=>$notice,'isRead'=>true]);
    }
    private function detailCheck(array $p,int $notice):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM AppNotice n WHERE n.AppNoticeID=? AND n.IsActive=1 AND EXISTS(SELECT 1 FROM AppParentStudent l JOIN StudentSession ss ON ss.StudentID=l.StudentID WHERE l.AppParentID=? AND (n.OwnerSessionID IS NULL OR n.OwnerSessionID=ss.OwnerSessionID) AND (n.ClassID IS NULL OR n.ClassID=ss.ClassID) AND (n.SectionID IS NULL OR n.SectionID=ss.SectionID) AND (n.StudentID IS NULL OR n.StudentID=ss.StudentID) AND ss.IsLeave=0)');$st->execute([$notice,$p['AppParentID']]);if(!(int)$st->fetchColumn())Response::error('Notice not found.',404,'NOTICE_NOT_FOUND');}
}
