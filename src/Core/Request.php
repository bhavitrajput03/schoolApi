<?php
declare(strict_types=1);
namespace App\Core;
final class Request {
    private ?array $json=null;
    public function method(): string { return strtoupper($_SERVER['REQUEST_METHOD']??'GET'); }
    public function path(): string {
        $path=rawurldecode(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/');
        $dir=rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME']??'')),'/');
        if ($dir!=='' && str_starts_with($path,$dir)) $path=substr($path,strlen($dir));
        return '/'.trim($path,'/');
    }
    public function json(): array {
        if ($this->json!==null) return $this->json; $raw=file_get_contents('php://input')?:'';
        if ($raw==='') return $this->json=[];
        try { $d=json_decode($raw,true,64,JSON_THROW_ON_ERROR); } catch (\JsonException) { Response::error('Invalid JSON body.',400,'INVALID_JSON'); }
        if (!is_array($d)) Response::error('JSON body must be an object.',400,'INVALID_JSON');
        return $this->json=$d;
    }
    public function query(string $key,mixed $default=null): mixed { return $_GET[$key]??$default; }
    public function header(string $name): ?string {
        $key=strtoupper(str_replace('-','_',$name));
        $value=$_SERVER['HTTP_'.$key]??$_SERVER['REDIRECT_HTTP_'.$key]??null;
        if ($value===null && strcasecmp($name,'Authorization')===0) $value=$_SERVER['AUTHORIZATION']??$_SERVER['REDIRECT_AUTHORIZATION']??null;
        if ($value===null && function_exists('getallheaders')) {
            foreach (getallheaders() as $header=>$headerValue) {
                if (strcasecmp((string)$header,$name)===0) { $value=$headerValue; break; }
            }
        }
        return is_string($value)?trim($value):null;
    }
    public function ip(): string { return substr($_SERVER['REMOTE_ADDR']??'unknown',0,45); }
}
