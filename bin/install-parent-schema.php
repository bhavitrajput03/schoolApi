<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

use App\Core\Database;

if(PHP_SAPI!=='cli'||$argc!==2){
    fwrite(STDERR,"Usage: php bin/install-parent-schema.php SCHOOL_CODE\n");
    exit(1);
}

$school=strtoupper(trim((string)$argv[1]));
$file=dirname(__DIR__).'/database/004_parent_app.sql';
$sql=file_get_contents($file);
if($sql===false){fwrite(STDERR,"Could not read database/004_parent_app.sql\n");exit(1);}

try{
    Database::useSchoolCode($school);
    $db=Database::connection();
    foreach(preg_split('/^\s*GO\s*$/mi',$sql)?:[] as $batch){
        $batch=trim($batch);
        if($batch!=='')$db->exec($batch);
    }
    $count=(int)$db->query("SELECT COUNT(*) FROM sys.tables WHERE name IN('AppParent','AppParentStudent','AppParentAuthToken','AppHomework','AppClassTeacher','AppNotice','AppNoticeRead','AppNotification','AppNotificationRead','AppParentDevice','AppParentSetting','AppPayment','AppReceipt','AppTeacherDevice','AppChatConversation','AppChatMessage','AppChatAttachment')")->fetchColumn();
    $identityCount=(int)$db->query("SELECT COUNT(*) FROM (VALUES
      ('AppParent','AppParentID'),('AppParentStudent','AppParentStudentID'),('AppParentAuthToken','AppParentAuthTokenID'),
      ('AppHomework','AppHomeworkID'),('AppClassTeacher','AppClassTeacherID'),('AppNotice','AppNoticeID'),
      ('AppNoticeRead','AppNoticeReadID'),('AppNotification','AppNotificationID'),('AppNotificationRead','AppNotificationReadID'),
      ('AppParentDevice','AppParentDeviceID'),('AppParentSetting','AppParentSettingID'),('AppPayment','AppPaymentID'),
      ('AppReceipt','AppReceiptID'),('AppTeacherDevice','AppTeacherDeviceID'),('AppChatConversation','AppChatConversationID'),
      ('AppChatMessage','AppChatMessageID'),('AppChatAttachment','AppChatAttachmentID')
    ) expected(TableName,ColumnName)
    JOIN sys.tables t ON t.name=expected.TableName AND SCHEMA_NAME(t.schema_id)='dbo'
    JOIN sys.columns c ON c.object_id=t.object_id AND c.name=expected.ColumnName
    JOIN sys.types ty ON ty.user_type_id=c.user_type_id
    WHERE ty.name='int' AND c.is_identity=1 AND EXISTS(
      SELECT 1 FROM sys.indexes i JOIN sys.index_columns ic ON ic.object_id=i.object_id AND ic.index_id=i.index_id
      WHERE i.object_id=t.object_id AND i.is_primary_key=1 AND ic.column_id=c.column_id
    )")->fetchColumn();
    if($count!==17||$identityCount!==17)throw new RuntimeException("Schema exists but is not the required INT IDENTITY design (tables {$count}/17, IDs {$identityCount}/17). Run database/007_rebuild_empty_parent_schema_as_int.sql first if these tables are empty.");
    echo "Parent schema installed for {$school}. Tables ready: {$count}/17; INT IDENTITY primary IDs: {$identityCount}/17\n";
}catch(Throwable $error){
    fwrite(STDERR,"Parent schema installation failed: {$error->getMessage()}\n");
    exit(1);
}
