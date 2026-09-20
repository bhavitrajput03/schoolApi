<?php
declare(strict_types=1);
namespace App\Controller;

use App\Core\{Database,Request,Response,Validator};
use App\Service\FcmService;

final class TeacherChatController {
    public function inbox(Request $request,array $user):never{
        $teacher=(string)$user['ApiUserID'];
        $sql="SELECT c.AppChatConversationID conversationId,c.StudentID studentId,s.StudentName studentName,
                     s.AdmissionNo admissionNo,c.AppParentID parentId,p.DisplayName parentName,
                     lastMessage.Message lastMessage,lastMessage.SenderType lastSenderType,
                     CONVERT(varchar(19),DATEADD(minute,330,lastMessage.SentAt),120) lastMessageAt,
                     (SELECT COUNT(*) FROM AppChatMessage unread
                      WHERE unread.AppChatConversationID=c.AppChatConversationID
                        AND unread.SenderType='parent' AND unread.ReadAt IS NULL) unreadCount,
                     CONVERT(varchar(19),DATEADD(minute,330,c.CreatedAt),120) createdAt,
                     CONVERT(varchar(19),DATEADD(minute,330,c.UpdatedAt),120) updatedAt
              FROM AppChatConversation c
              JOIN Student s ON s.StudentID=c.StudentID
              JOIN AppParent p ON p.AppParentID=c.AppParentID
              OUTER APPLY(
                  SELECT TOP 1 m.Message,m.SenderType,m.SentAt
                  FROM AppChatMessage m
                  WHERE m.AppChatConversationID=c.AppChatConversationID
                  ORDER BY m.SentAt DESC,m.AppChatMessageID DESC
              ) lastMessage
              WHERE c.TeacherApiUserID=?
              ORDER BY COALESCE(lastMessage.SentAt,c.UpdatedAt) DESC,c.AppChatConversationID DESC";
        $st=Database::connection()->prepare($sql);$st->execute([$teacher]);
        Response::success(['conversations'=>$st->fetchAll()]);
    }

    public function messages(Request $request,array $user,int|string $conversation):never{
        self::assertConversation($conversation,$user['ApiUserID']);
        $sql="SELECT m.AppChatMessageID id,m.SenderType senderType,m.SenderID senderId,m.Message message,
                     CONVERT(varchar(19),DATEADD(minute,330,m.SentAt),120) sentAt,
                     CONVERT(varchar(19),DATEADD(minute,330,m.ReadAt),120) readAt,
                     a.AppChatAttachmentID attachmentId,
                     a.FileName fileName,a.MimeType mimeType,a.FileUrl fileUrl,a.FileSize fileSize
              FROM AppChatMessage m
              LEFT JOIN AppChatAttachment a ON a.AppChatMessageID=m.AppChatMessageID
              WHERE m.AppChatConversationID=?
              ORDER BY m.SentAt,m.AppChatMessageID";
        $st=Database::connection()->prepare($sql);$st->execute([$conversation]);
        Response::success(['conversationId'=>$conversation,'messages'=>$st->fetchAll()]);
    }

    public function reply(Request $request,array $user,int|string $conversation):never{
        self::assertConversation($conversation,$user['ApiUserID']);
        $data=$request->json();Validator::required($data,['message']);$message=trim((string)$data['message']);
        if($message===''||mb_strlen($message)>5000)Response::error('message must contain 1 to 5000 characters.',422,'VALIDATION_ERROR');
        $db=Database::connection();$db->beginTransaction();
        try{
            $context=$db->prepare('SELECT c.AppParentID,c.StudentID,s.StudentName FROM AppChatConversation c JOIN Student s ON s.StudentID=c.StudentID WHERE c.AppChatConversationID=? AND c.TeacherApiUserID=?');$context->execute([$conversation,(string)$user['ApiUserID']]);$chat=$context->fetch();
            $st=$db->prepare("INSERT INTO AppChatMessage(AppChatConversationID,SenderType,SenderID,Message)
                              OUTPUT INSERTED.AppChatMessageID,CONVERT(varchar(19),DATEADD(minute,330,INSERTED.SentAt),120) sentAt
                              VALUES(?,'teacher',?,?)");
            $st->execute([$conversation,(string)$user['ApiUserID'],$message]);$created=$st->fetch();
            $db->prepare('UPDATE AppChatConversation SET UpdatedAt=SYSUTCDATETIME() WHERE AppChatConversationID=?')->execute([$conversation]);
            $db->prepare("INSERT INTO AppNotification(AppParentID,StudentID,NotificationType,Title,Body,RelatedType,RelatedID) VALUES(?,?,'chat',?,?,'chat',?)")->execute([$chat['AppParentID'],$chat['StudentID'],'New message from '.$user['DisplayName'],mb_substr($message,0,1000),(string)$conversation]);
            $device=$db->prepare("SELECT DISTINCT FcmToken FROM AppParentDevice WHERE AppParentID=? AND IsActive=1 AND FcmToken<>''");$device->execute([$chat['AppParentID']]);$tokens=$device->fetchAll(\PDO::FETCH_COLUMN);
            $db->commit();
        }catch(\Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
        $push=FcmService::chat($tokens,$conversation,(int)$chat['StudentID'],'New message from '.$user['DisplayName'],$message);
        Response::success(['conversationId'=>$conversation,'message'=>['id'=>(int)$created['AppChatMessageID'],'senderType'=>'teacher','senderId'=>self::identifier($user['ApiUserID']),'message'=>$message,'sentAt'=>$created['sentAt']],'push'=>$push],201);
    }

    public function read(Request $request,array $user,int|string $conversation):never{
        self::assertConversation($conversation,$user['ApiUserID']);
        $st=Database::connection()->prepare("UPDATE AppChatMessage SET ReadAt=SYSUTCDATETIME()
                                             WHERE AppChatConversationID=? AND SenderType='parent' AND ReadAt IS NULL");
        $st->execute([$conversation]);Response::success(['conversationId'=>$conversation,'updated'=>$st->rowCount()]);
    }

    private static function assertConversation(int|string $conversation,string|int $teacher):void{
        $st=Database::connection()->prepare('SELECT COUNT(*) FROM AppChatConversation WHERE AppChatConversationID=? AND TeacherApiUserID=?');
        $st->execute([$conversation,(string)$teacher]);
        if(!(int)$st->fetchColumn())Response::error('Conversation not found.',404,'CHAT_NOT_FOUND');
    }

    private static function identifier(mixed $value):string|int{return is_int($value)||is_string($value)&&ctype_digit($value)?(int)$value:(string)$value;}
}
