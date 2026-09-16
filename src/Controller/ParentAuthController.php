<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,ParentAuth,Request,Response,Validator};
use App\Service\SchoolService;

final class ParentAuthController {
    public function login(Request $r):never{
        $d=$r->json();Validator::required($d,['schoolCode','password']);
        $code=trim((string)($d['parentCode']??$d['userId']??''));if($code==='')Response::error('parentCode or userId is required.',422,'VALIDATION_ERROR');
        $school=strtoupper(trim((string)$d['schoolCode']));try{Database::useSchoolCode($school);}catch(\DomainException){Response::error('Invalid school code, user ID or password.',401,'INVALID_CREDENTIALS');}
        $db=Database::connection();$st=$db->prepare('SELECT p.* FROM AppParent p WHERE LOWER(p.ParentCode)=LOWER(?) AND p.IsActive=1');$st->execute([$code]);$parent=$st->fetch();
        $provided=(string)$d['password'];$stored=(string)($parent['PasswordHash']??'');
        if(!$parent||!self::matches($provided,$stored))Response::error('Invalid school code, user ID or password.',401,'INVALID_CREDENTIALS');
        $algo=defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT;$info=password_get_info($stored);
        if(($info['algoName']??'unknown')==='unknown'||password_needs_rehash($stored,$algo))$db->prepare('UPDATE AppParent SET PasswordHash=?,PasswordChangedAt=SYSUTCDATETIME() WHERE AppParentID=?')->execute([password_hash($provided,$algo),$parent['AppParentID']]);
        $db->prepare('UPDATE AppParent SET LastLoginAt=SYSUTCDATETIME() WHERE AppParentID=?')->execute([$parent['AppParentID']]);unset($parent['PasswordHash']);
        Response::success(['parent'=>$parent,'academicSession'=>SchoolService::currentSession(),'auth'=>ParentAuth::issue((string)$parent['AppParentID'],$r)]);
    }
    public function refresh(Request $r):never{$d=$r->json();Validator::required($d,['refreshToken']);Response::success(['auth'=>ParentAuth::refresh((string)$d['refreshToken'],$r)]);}
    public function logout(Request $r,array $p):never{Database::connection()->prepare('UPDATE AppParentAuthToken SET RevokedAt=SYSUTCDATETIME() WHERE AccessTokenHash=?')->execute([$p['_tokenHash']]);Response::success(['message'=>'Logged out.']);}
    private static function matches(string $provided,string $stored):bool{$i=password_get_info($stored);return($i['algoName']??'unknown')==='unknown'?hash_equals($stored,$provided):password_verify($provided,$stored);}
}
