<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response,Validator};
use App\Service\ParentService;

final class NotificationController {
    public function list(Request $r,array $p):never{
        $st=Database::connection()->prepare("SELECT n.AppNotificationID id,n.NotificationType type,n.Title title,n.Body body,n.RelatedType relatedType,n.RelatedID relatedId,n.CreatedAt createdAt,CAST(CASE WHEN rd.AppNotificationID IS NULL THEN 0 ELSE 1 END AS bit) isRead
          FROM AppNotification n LEFT JOIN AppNotificationRead rd ON rd.AppNotificationID=n.AppNotificationID AND rd.AppParentID=?
          WHERE n.AppParentID=? OR n.StudentID IN(SELECT StudentID FROM AppParentStudent WHERE AppParentID=?) OR (n.AppParentID IS NULL AND n.StudentID IS NULL) ORDER BY n.CreatedAt DESC");$st->execute([$p['AppParentID'],$p['AppParentID'],$p['AppParentID']]);Response::success(['notifications'=>$st->fetchAll()]);
    }
    public function unread(Request $r,array $p):never{
        $st=Database::connection()->prepare('SELECT COUNT(*) FROM AppNotification n WHERE (n.AppParentID=? OR n.StudentID IN(SELECT StudentID FROM AppParentStudent WHERE AppParentID=?) OR (n.AppParentID IS NULL AND n.StudentID IS NULL)) AND NOT EXISTS(SELECT 1 FROM AppNotificationRead rd WHERE rd.AppNotificationID=n.AppNotificationID AND rd.AppParentID=?)');$st->execute([$p['AppParentID'],$p['AppParentID'],$p['AppParentID']]);Response::success(['unreadCount'=>(int)$st->fetchColumn()]);
    }
    public function read(Request $r,array $p,int $notification):never{
        $this->allowed($p,$notification);Database::connection()->prepare('IF NOT EXISTS(SELECT 1 FROM AppNotificationRead WHERE AppParentID=? AND AppNotificationID=?) INSERT INTO AppNotificationRead(AppParentID,AppNotificationID) VALUES(?,?)')->execute([$p['AppParentID'],$notification,$p['AppParentID'],$notification]);Response::success(['notificationId'=>$notification,'isRead'=>true]);
    }
    public function readAll(Request $r,array $p):never{
        $st=Database::connection()->prepare('INSERT INTO AppNotificationRead(AppParentID,AppNotificationID) SELECT ?,n.AppNotificationID FROM AppNotification n WHERE (n.AppParentID=? OR n.StudentID IN(SELECT StudentID FROM AppParentStudent WHERE AppParentID=?) OR (n.AppParentID IS NULL AND n.StudentID IS NULL)) AND NOT EXISTS(SELECT 1 FROM AppNotificationRead rd WHERE rd.AppParentID=? AND rd.AppNotificationID=n.AppNotificationID)');$st->execute([$p['AppParentID'],$p['AppParentID'],$p['AppParentID'],$p['AppParentID']]);Response::success(['updated'=>$st->rowCount()]);
    }
    public function registerDevice(Request $r,array $p):never{
        $d=$r->json();Validator::required($d,['deviceId','fcmToken','platform']);if(!in_array(strtolower((string)$d['platform']),['android','ios','web'],true))Response::error('platform must be android, ios or web.',422,'VALIDATION_ERROR');
        $sql='UPDATE AppParentDevice WITH (UPDLOCK,HOLDLOCK) SET FcmToken=?,Platform=?,AppVersion=?,IsActive=1,UpdatedAt=SYSUTCDATETIME() WHERE AppParentID=? AND DeviceKey=?; IF @@ROWCOUNT=0 INSERT INTO AppParentDevice(AppParentID,DeviceKey,FcmToken,Platform,AppVersion) VALUES(?,?,?,?,?)';
        Database::connection()->prepare($sql)->execute([(string)$d['fcmToken'],strtolower((string)$d['platform']),$d['appVersion']??null,$p['AppParentID'],(string)$d['deviceId'],$p['AppParentID'],(string)$d['deviceId'],(string)$d['fcmToken'],strtolower((string)$d['platform']),$d['appVersion']??null]);Response::success(['deviceId'=>(string)$d['deviceId'],'registered'=>true]);
    }
    public function removeDevice(Request $r,array $p,int $device):never{$st=Database::connection()->prepare('UPDATE AppParentDevice SET IsActive=0,UpdatedAt=SYSUTCDATETIME() WHERE AppParentDeviceID=? AND AppParentID=?');$st->execute([$device,$p['AppParentID']]);if(!$st->rowCount())Response::error('Device not found.',404,'DEVICE_NOT_FOUND');Response::success(['removed'=>true]);}
    private function allowed(array $p,int $id):void{$st=Database::connection()->prepare('SELECT COUNT(*) FROM AppNotification n WHERE n.AppNotificationID=? AND (n.AppParentID=? OR n.StudentID IN(SELECT StudentID FROM AppParentStudent WHERE AppParentID=?) OR (n.AppParentID IS NULL AND n.StudentID IS NULL))');$st->execute([$id,$p['AppParentID'],$p['AppParentID']]);if(!(int)$st->fetchColumn())Response::error('Notification not found.',404,'NOTIFICATION_NOT_FOUND');}
}
