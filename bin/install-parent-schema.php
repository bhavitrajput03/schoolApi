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
    $count=(int)$db->query("SELECT COUNT(*) FROM sys.tables WHERE name IN('AppParent','AppParentStudent','AppParentAuthToken','AppHomework','AppClassTeacher','AppNotice','AppNoticeRead','AppNotification','AppNotificationRead','AppParentDevice','AppParentSetting','AppPayment','AppReceipt')")->fetchColumn();
    echo "Parent schema installed for {$school}. Tables ready: {$count}/13\n";
}catch(Throwable $error){
    fwrite(STDERR,"Parent schema installation failed: {$error->getMessage()}\n");
    exit(1);
}
