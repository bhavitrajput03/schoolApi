<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'||$argc<7){fwrite(STDERR,"Usage: php bin/assign-class-teacher.php SCHOOL_CODE TEACHER_USERNAME OWNER_SESSION_ID CLASS_ID SECTION_ID class_teacher|co_class_teacher\n");exit(1);}
[, $school,$username,$session,$class,$section,$role]=$argv;
if(!in_array($role,['class_teacher','co_class_teacher'],true)){fwrite(STDERR,"Invalid teacher role.\n");exit(1);}
Database::useSchoolCode($school);$db=Database::connection();
$st=$db->prepare('SELECT ApiUserID FROM SchoolTeacher WHERE LOWER(Username)=LOWER(?) AND IsActive=1');$st->execute([$username]);$teacher=$st->fetchColumn();
if(!$teacher){fwrite(STDERR,"Teacher not found.\n");exit(1);}
$sql='IF EXISTS(SELECT 1 FROM AppClassTeacher WHERE OwnerSessionID=? AND ClassID=? AND SectionID=? AND ApiUserID=? AND TeacherRole=?) UPDATE AppClassTeacher SET IsActive=1 WHERE OwnerSessionID=? AND ClassID=? AND SectionID=? AND ApiUserID=? AND TeacherRole=? ELSE INSERT INTO AppClassTeacher(OwnerSessionID,ClassID,SectionID,ApiUserID,TeacherRole) VALUES(?,?,?,?,?)';
$db->prepare($sql)->execute([$session,$class,$section,$teacher,$role,$session,$class,$section,$teacher,$role,$session,$class,$section,$teacher,$role]);
echo "Class teacher assignment saved.\n";
