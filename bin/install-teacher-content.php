<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

use App\Core\Database;

if(PHP_SAPI!=='cli'||$argc!==2){fwrite(STDERR,"Usage: php bin/install-teacher-content.php SCHOOL_CODE\n");exit(1);}
$school=strtoupper(trim((string)$argv[1]));
try{
    Database::useSchoolCode($school);$db=Database::connection();
    $tables=(int)$db->query("SELECT COUNT(*) FROM sys.tables WHERE schema_id=SCHEMA_ID('dbo') AND name IN('AppNotice','AppHomework')")->fetchColumn();
    if($tables!==2)throw new RuntimeException("AppNotice/AppHomework tables are missing. Run database/004_parent_app.sql as dbo/sa first.");
    $ready=(int)$db->query("SELECT COUNT(*) FROM sys.columns WHERE object_id IN(OBJECT_ID(N'dbo.AppNotice'),OBJECT_ID(N'dbo.AppHomework')) AND name IN('NoticeType','TargetType','CreatedByApiUserID','AttachmentUrl')")->fetchColumn();
    if($ready===6){echo "Teacher notice/homework schema ready for {$school}. Required columns found: {$ready}/6\n";exit(0);}
    $sql=file_get_contents(dirname(__DIR__).'/database/008_teacher_notice_homework.sql');
    if($sql===false)throw new RuntimeException('Could not read teacher-content migration.');
    foreach(preg_split('/^\s*GO\s*$/mi',$sql)?:[] as $batch){$batch=trim($batch);if($batch!=='')$db->exec($batch);}
    $ready=(int)$db->query("SELECT COUNT(*) FROM sys.columns WHERE object_id IN(OBJECT_ID(N'dbo.AppNotice'),OBJECT_ID(N'dbo.AppHomework')) AND name IN('NoticeType','TargetType','CreatedByApiUserID','AttachmentUrl')")->fetchColumn();
    if($ready!==6)throw new RuntimeException("Teacher notice/homework schema is incomplete. Required columns found: {$ready}/6");
    echo "Teacher notice/homework schema installed for {$school}. Required columns found: {$ready}/6\n";
}catch(Throwable $error){fwrite(STDERR,"Teacher notice/homework installation failed: {$error->getMessage()}\n");exit(1);}
