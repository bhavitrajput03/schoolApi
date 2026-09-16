<?php
declare(strict_types=1);
namespace App\Service;

final class LegacyMapper {
    public static function value(array $row,array $names,mixed $default=null):mixed{
        $lower=array_change_key_case($row,CASE_LOWER);foreach($names as $name){$key=strtolower($name);if(array_key_exists($key,$lower)&&$lower[$key]!==null)return$lower[$key];}return$default;
    }
    public static function money(mixed $value):float{return is_numeric($value)?round((float)$value,2):0.0;}
    public static function date(mixed $value):?string{if(!$value)return null;try{return(new \DateTimeImmutable((string)$value))->format('Y-m-d');}catch(\Throwable){return null;}}
}
