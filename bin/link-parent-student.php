<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'||$argc<4){fwrite(STDERR,"Usage: php bin/link-parent-student.php SCHOOL_CODE PARENT_CODE STUDENT_ID [RELATIONSHIP] [IS_PRIMARY]\n");exit(1);}
[, $school,$code,$student]=$argv;Database::useSchoolCode($school);$db=Database::connection();$st=$db->prepare('SELECT AppParentID FROM AppParent WHERE LOWER(ParentCode)=LOWER(?) AND IsActive=1');$st->execute([$code]);$parent=$st->fetchColumn();if(!$parent){fwrite(STDERR,"Parent not found.\n");exit(1);}
$db->prepare('IF NOT EXISTS(SELECT 1 FROM AppParentStudent WHERE AppParentID=? AND StudentID=?) INSERT INTO AppParentStudent(AppParentID,StudentID,Relationship,IsPrimary) VALUES(?,?,?,?) ELSE UPDATE AppParentStudent SET Relationship=?,IsPrimary=? WHERE AppParentID=? AND StudentID=?')->execute([$parent,$student,$parent,$student,$argv[4]??null,(int)($argv[5]??0),$argv[4]??null,(int)($argv[5]??0),$parent,$student]);echo"Student linked.\n";
