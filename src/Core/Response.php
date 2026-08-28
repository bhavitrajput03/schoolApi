<?php
declare(strict_types=1);
namespace App\Core;
final class Response {
    public static function json(array $data,int $status=200): never {
        http_response_code($status); header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE); exit;
    }
    public static function success(mixed $data=null,int $status=200): never { self::json(['success'=>true,'data'=>$data],$status); }
    public static function error(string $message,int $status,string $code,array $details=[]): never {
        $e=['code'=>$code,'message'=>$message]; if ($details) $e['details']=$details; self::json(['success'=>false,'error'=>$e],$status);
    }
}

