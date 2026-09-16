<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\{Database,LoginPassword};
if(PHP_SAPI!=='cli'||$argc<5){fwrite(STDERR,"Usage: php bin/create-parent.php SCHOOL_CODE PARENT_CODE DISPLAY_NAME PASSWORD [MOBILE] [EMAIL] [SCHOOL_BRANCH_ID]\n");exit(1);}
[, $school,$code,$name,$password]=$argv;Database::useSchoolCode($school);$db=Database::connection();$branch=$argv[7]??null;
if($branch===null){$branches=$db->query('SELECT TOP 2 SchoolBranchID FROM SchoolBranch ORDER BY SchoolBranchID')->fetchAll(\PDO::FETCH_COLUMN);if(count($branches)!==1){fwrite(STDERR,"Tenant has multiple/no branches; pass SCHOOL_BRANCH_ID explicitly.\n");exit(1);}$branch=$branches[0];}
$hash=LoginPassword::hash($password);
$db->prepare('INSERT INTO AppParent(SchoolBranchID,ParentCode,DisplayName,Mobile,Email,PasswordHash) VALUES(?,?,?,?,?,?)')->execute([$branch,$code,$name,$argv[5]??null,$argv[6]??null,$hash]);echo"Parent created.\n";
