<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'||$argc!==7){fwrite(STDERR,"Usage: php bin/assign-teacher.php SCHOOL_CODE USERNAME SESSION_ID CLASS_ID SECTION_ID SUBJECT_ID\n");exit(1);}
[, $school,$username,$session,$class,$section,$subject]=$argv;Database::useSchoolCode($school);$db=Database::connection();$st=$db->prepare('SELECT ApiUserID FROM SchoolTeacher WHERE Username=? AND IsActive=1');$st->execute([mb_strtolower($username)]);$id=$st->fetchColumn();if(!$id){fwrite(STDERR,"User not found.\n");exit(1);}
$db->prepare('IF NOT EXISTS(SELECT 1 FROM ApiTeacherAssignment WHERE ApiUserID=? AND OwnerSessionID=? AND ClassID=? AND SectionID=? AND SubjectID=?) INSERT INTO ApiTeacherAssignment(ApiUserID,OwnerSessionID,ClassID,SectionID,SubjectID) VALUES(?,?,?,?,?)')->execute([$id,$session,$class,$section,$subject,$id,$session,$class,$section,$subject]);
echo "Assignment saved.\n";
