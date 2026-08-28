<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Auth,Database,Request,Response,Validator};
final class AuthController {
    public function login(Request $r): never {
        $d=$r->json();Validator::required($d,['schoolCode','username','password']);$school=strtoupper(trim((string)$d['schoolCode']));$username=mb_strtolower(trim((string)$d['username']));
        $key=hash('sha256',"$school|$username|".$r->ip());$db=Database::connection();$st=$db->prepare('SELECT COUNT(*) FROM ApiLoginAttempt WHERE AttemptKey=? AND AttemptedAt>DATEADD(MINUTE,-15,SYSUTCDATETIME())');$st->execute([$key]);
        if((int)$st->fetchColumn()>=5)Response::error('Too many login attempts. Try again after 15 minutes.',429,'RATE_LIMITED');
        $st=$db->prepare('SELECT u.ApiUserID,u.PasswordHash,u.DisplayName,u.Username,u.Role,u.EmployeeID,u.SchoolBranchID FROM ApiUser u JOIN ApiSchool s ON s.SchoolBranchID=u.SchoolBranchID WHERE s.SchoolCode=? AND LOWER(u.Username)=? AND u.IsActive=1');$st->execute([$school,$username]);$user=$st->fetch();
        if(!$user||!password_verify((string)$d['password'],$user['PasswordHash'])){$db->prepare('INSERT INTO ApiLoginAttempt(AttemptKey,AttemptedAt) VALUES(?,SYSUTCDATETIME())')->execute([$key]);Response::error('Invalid school code, username or password.',401,'INVALID_CREDENTIALS');}
        $db->prepare('DELETE FROM ApiLoginAttempt WHERE AttemptKey=?')->execute([$key]);$db->prepare('UPDATE ApiUser SET LastLoginAt=SYSUTCDATETIME() WHERE ApiUserID=?')->execute([$user['ApiUserID']]);unset($user['PasswordHash']);
        Response::success(['user'=>$user,'auth'=>Auth::issue($user['ApiUserID'],$r)]);
    }
    public function logout(Request $r,array $user): never { Database::connection()->prepare('UPDATE ApiAuthToken SET RevokedAt=SYSUTCDATETIME() WHERE TokenHash=?')->execute([$user['_tokenHash']]);Response::success(['message'=>'Logged out.']); }
    public function me(Request $r,array $user): never { unset($user['_tokenHash']);Response::success($user); }
    public function changePassword(Request $r,array $user): never {
        $d=$r->json();Validator::required($d,['currentPassword','newPassword']);$new=(string)$d['newPassword'];
        if(strlen($new)<12||!preg_match('/[A-Z]/',$new)||!preg_match('/[a-z]/',$new)||!preg_match('/\d/',$new)||!preg_match('/[^A-Za-z0-9]/',$new))Response::error('New password must be at least 12 characters and include upper, lower, number and symbol.',422,'WEAK_PASSWORD');
        $db=Database::connection();$st=$db->prepare('SELECT PasswordHash FROM ApiUser WHERE ApiUserID=?');$st->execute([$user['ApiUserID']]);if(!password_verify((string)$d['currentPassword'],(string)$st->fetchColumn()))Response::error('Current password is incorrect.',422,'INVALID_PASSWORD');
        $hash=password_hash($new,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);$db->beginTransaction();
        try{$db->prepare('UPDATE ApiUser SET PasswordHash=?,PasswordChangedAt=SYSUTCDATETIME() WHERE ApiUserID=?')->execute([$hash,$user['ApiUserID']]);$db->prepare('UPDATE ApiAuthToken SET RevokedAt=SYSUTCDATETIME() WHERE ApiUserID=? AND TokenHash<>? AND RevokedAt IS NULL')->execute([$user['ApiUserID'],$user['_tokenHash']]);$db->commit();}catch(\Throwable $e){$db->rollBack();throw$e;}
        Response::success(['message'=>'Password changed. Other sessions were signed out.']);
    }
}

