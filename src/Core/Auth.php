<?php
declare(strict_types=1);
namespace App\Core;
final class Auth {
    public static function user(Request $request):array{
        $header=$request->header('Authorization')??'';
        if(!preg_match('/^Bearer\s+([A-Za-z0-9_-]{1,80}\.[A-Za-z0-9_-]{40,})$/',$header,$match))Response::error('Authentication required.',401,'UNAUTHENTICATED');
        $token=$match[1];$school=self::schoolFromToken($token);
        try{Database::useSchoolCode($school);}catch(\DomainException){Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');}
        $hash=hash('sha256',$token);$sql="SELECT u.ApiUserID,u.EmployeeID,u.SchoolBranchID,u.DisplayName,u.Username,u.Role FROM ApiAuthToken t JOIN SchoolTeacher u ON u.ApiUserID=t.ApiUserID WHERE t.TokenHash=? AND t.RevokedAt IS NULL AND t.ExpiresAt>SYSUTCDATETIME() AND u.IsActive=1";
        $st=Database::connection()->prepare($sql);$st->execute([$hash]);$user=$st->fetch();if(!$user)Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');
        Database::connection()->prepare('UPDATE ApiAuthToken SET LastUsedAt=SYSUTCDATETIME() WHERE TokenHash=?')->execute([$hash]);$user['_tokenHash']=$hash;return$user;
    }
    public static function issue(string|int $userId,Request $request):array{
        $prefix=rtrim(strtr(base64_encode(Database::schoolCode()),'+/','-_'),'=');$token=$prefix.'.'.rtrim(strtr(base64_encode(random_bytes(48)),'+/','-_'),'=');$minutes=max(15,min(43200,(int)Env::get('TOKEN_TTL_MINUTES','10080')));
        Database::connection()->prepare('INSERT INTO ApiAuthToken(ApiUserID,TokenHash,ExpiresAt,IpAddress,UserAgent) VALUES(?,?,DATEADD(MINUTE,CAST(? AS int),SYSUTCDATETIME()),?,?)')->execute([$userId,hash('sha256',$token),$minutes,$request->ip(),substr($request->header('User-Agent')??'',0,500)]);
        return['accessToken'=>$token,'tokenType'=>'Bearer','expiresIn'=>$minutes*60];
    }
    private static function schoolFromToken(string $token):string{
        [$encoded]=explode('.',$token,2);$padding=str_repeat('=',(4-strlen($encoded)%4)%4);$school=base64_decode(strtr($encoded.$padding,'-_','+/'),true);
        if($school===false||!preg_match('/^[A-Z0-9_-]{1,50}$/',$school))Response::error('Token is invalid or expired.',401,'INVALID_TOKEN');return$school;
    }
}
