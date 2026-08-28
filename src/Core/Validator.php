<?php
declare(strict_types=1);
namespace App\Core;
final class Validator {
    public static function required(array $data,array $keys): void {
        $m=[]; foreach($keys as $k) if(!array_key_exists($k,$data)||$data[$k]===''||$data[$k]===null)$m[]=$k;
        if($m)Response::error('Validation failed.',422,'VALIDATION_ERROR',['missing'=>$m]);
    }
    public static function id(mixed $v,string $field): int {
        if(filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)Response::error("$field must be a positive integer.",422,'VALIDATION_ERROR');
        return (int)$v;
    }
    public static function date(mixed $v,string $field='date'): string {
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d',(string)$v);
        if(!$d||$d->format('Y-m-d')!==$v)Response::error("$field must be YYYY-MM-DD.",422,'VALIDATION_ERROR');
        return (string)$v;
    }
}

