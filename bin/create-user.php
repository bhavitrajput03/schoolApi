<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\{Database,LoginPassword};
if(PHP_SAPI!=='cli'||$argc<6){fwrite(STDERR,"Usage: php bin/create-user.php SCHOOL_CODE USERNAME DISPLAY_NAME PASSWORD ROLE [EMPLOYEE_ID] [SCHOOL_BRANCH_ID]\n");exit(1);}
[, $school,$username,$name,$password,$role]=$argv;
if(strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/\d/',$password)||!preg_match('/[^A-Za-z0-9]/',$password)){fwrite(STDERR,"Password must be 12+ chars with upper, lower, number and symbol.\n");exit(1);}
if(!in_array($role,['teacher','admin'],true)){fwrite(STDERR,"Role must be teacher or admin.\n");exit(1);}
Database::useSchoolCode($school);$db=Database::connection();$branch=$argv[7]??null;
if($branch===null){$branches=$db->query('SELECT TOP 2 SchoolBranchID FROM SchoolBranch ORDER BY SchoolBranchID')->fetchAll(\PDO::FETCH_COLUMN);if(count($branches)!==1){fwrite(STDERR,"Tenant has multiple/no branches; pass SCHOOL_BRANCH_ID explicitly.\n");exit(1);}$branch=$branches[0];}
$hash=LoginPassword::hash($password);
$db->prepare('INSERT INTO SchoolTeacher(EmployeeID,SchoolBranchID,DisplayName,Username,PasswordHash,Role) VALUES(?,?,?,?,?,?)')->execute([$argv[6]??null,$branch,$name,mb_strtolower($username),$hash,$role]);
echo "API user created successfully.\n";
