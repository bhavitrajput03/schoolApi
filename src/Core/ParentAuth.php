<?php
declare(strict_types=1);
namespace App\Core;
use PDO;

final class ParentAuth {
    public static function user(Request $request):array{
        $header=$request->header('Authorization')??'';
        if(!preg_match('/^Bearer\s+([A-Za-z0-9_-]{1,80}\.[A-Za-z0-9_-]{40,})$/',$header,$match))
            Response::error('Authentication required.',401,'UNAUTHENTICATED');
        $token=$match[1];self::selectSchool($token);$hash=hash('sha256',$token);
        $sql="SELECT p.AppParentID,p.SchoolBranchID,p.ParentCode,p.DisplayName,p.Mobile,p.Email
              FROM AppParentAuthToken t JOIN AppParent p ON p.AppParentID=t.AppParentID
              WHERE t.AccessTokenHash=? AND t.RevokedAt IS NULL
                AND t.AccessExpiresAt>SYSUTCDATETIME() AND p.IsActive=1";
        $st=Database::connection()->prepare($sql);$st->execute([$hash]);$parent=$st->fetch();
        if(!$parent)Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');
        Database::connection()->prepare('UPDATE AppParentAuthToken SET LastUsedAt=SYSUTCDATETIME() WHERE AccessTokenHash=?')->execute([$hash]);
        $parent['_tokenHash']=$hash;return$parent;
    }
    public static function issue(int $parentId,Request $request):array{
        $prefix=self::prefix('P:'.Database::schoolCode());
        $access=$prefix.'.'.self::random();$refresh=$prefix.'.'.self::random();
        $accessMinutes=max(15,min(1440,(int)Env::get('PARENT_ACCESS_TTL_MINUTES','60')));
        $refreshDays=max(1,min(365,(int)Env::get('PARENT_REFRESH_TTL_DAYS','30')));
        Database::connection()->prepare(
            'INSERT INTO AppParentAuthToken(AppParentID,AccessTokenHash,RefreshTokenHash,AccessExpiresAt,RefreshExpiresAt,IpAddress,UserAgent)
             VALUES(?,?,?,DATEADD(MINUTE,CAST(? AS int),SYSUTCDATETIME()),DATEADD(DAY,CAST(? AS int),SYSUTCDATETIME()),?,?)'
        )->execute([$parentId,hash('sha256',$access),hash('sha256',$refresh),$accessMinutes,$refreshDays,$request->ip(),substr($request->header('User-Agent')??'',0,500)]);
        return['accessToken'=>$access,'refreshToken'=>$refresh,'tokenType'=>'Bearer','expiresIn'=>$accessMinutes*60];
    }
    public static function refresh(string $refreshToken,Request $request):array{
        self::selectSchool($refreshToken);$hash=hash('sha256',$refreshToken);$db=Database::connection();$db->beginTransaction();
        try{
            $st=$db->prepare('SELECT TOP 1 AppParentAuthTokenID,AppParentID FROM AppParentAuthToken WITH (UPDLOCK,HOLDLOCK) WHERE RefreshTokenHash=? AND RevokedAt IS NULL AND RefreshExpiresAt>SYSUTCDATETIME()');
            $st->execute([$hash]);$row=$st->fetch();if(!$row){$db->rollBack();Response::error('Refresh token is invalid or expired.',401,'INVALID_REFRESH_TOKEN');}
            $db->prepare('UPDATE AppParentAuthToken SET RevokedAt=SYSUTCDATETIME() WHERE AppParentAuthTokenID=?')->execute([$row['AppParentAuthTokenID']]);
            $tokens=self::issue((int)$row['AppParentID'],$request);$db->commit();return$tokens;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}
    }
    private static function selectSchool(string $token):void{
        [$encoded]=explode('.',$token,2);$padding=str_repeat('=',(4-strlen($encoded)%4)%4);
        $school=base64_decode(strtr($encoded.$padding,'-_','+/'),true);
        if($school===false||!str_starts_with($school,'P:'))Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');
        $school=substr($school,2);if(!preg_match('/^[A-Z0-9_-]{1,50}$/',$school))Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');
        try{Database::useSchoolCode($school);}catch(\DomainException){Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');}
    }
    private static function prefix(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
    private static function random():string{return rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');}
}
