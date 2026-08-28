<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\Database;
if(PHP_SAPI!=='cli'||$argc<6){fwrite(STDERR,"Usage: php bin/create-user.php SCHOOL_CODE USERNAME DISPLAY_NAME PASSWORD ROLE [EMPLOYEE_ID]\n");exit(1);}
[, $school,$username,$name,$password,$role]=$argv;
if(strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/\d/',$password)||!preg_match('/[^A-Za-z0-9]/',$password)){fwrite(STDERR,"Password must be 12+ chars with upper, lower, number and symbol.\n");exit(1);}
if(!in_array($role,['teacher','admin'],true)){fwrite(STDERR,"Role must be teacher or admin.\n");exit(1);}
$db=Database::connection();$st=$db->prepare('SELECT SchoolBranchID FROM ApiSchool WHERE SchoolCode=? AND IsActive=1');$st->execute([strtoupper($school)]);$branch=$st->fetchColumn();if(!$branch){fwrite(STDERR,"Unknown school code.\n");exit(1);}
$hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);
$db->prepare('INSERT INTO ApiUser(EmployeeID,SchoolBranchID,DisplayName,Username,PasswordHash,Role) VALUES(?,?,?,?,?,?)')->execute([$argv[6]??null,$branch,$name,mb_strtolower($username),$hash,$role]);
echo "API user created successfully.\n";

