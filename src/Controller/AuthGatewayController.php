<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Auth,ParentAuth,Request,Response};

final class AuthGatewayController {
    public function login(Request $r):never{$d=$r->json();if(isset($d['parentCode'])||isset($d['userId']))(new ParentAuthController())->login($r);(new AuthController())->login($r);}
    public function logout(Request $r):never{
        $header=$r->header('Authorization')??'';if(!preg_match('/^Bearer\s+([^\.]+)\./',$header,$m))Response::error('Authentication required.',401,'UNAUTHENTICATED');
        $encoded=$m[1];$padding=str_repeat('=',(4-strlen($encoded)%4)%4);$prefix=base64_decode(strtr($encoded.$padding,'-_','+/'),true);
        if(is_string($prefix)&&str_starts_with($prefix,'P:')){(new ParentAuthController())->logout($r,ParentAuth::user($r));}
        (new AuthController())->logout($r,Auth::user($r));
    }
}
