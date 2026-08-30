<?php
declare(strict_types=1);
namespace App\Core;
final class SchoolRegistry {
    private static array $cache=[];
    public static function find(string $schoolCode):?array{
        if(array_key_exists($schoolCode,self::$cache))return self::$cache[$schoolCode];
        $st=RegistryDatabase::connection()->prepare('SELECT TOP 1 ConnectionString FROM dbo.SchoolConnection WHERE SchoolCode=?');$st->execute([$schoolCode]);$row=$st->fetch();
        if(!$row)return self::$cache[$schoolCode]=null;
        return self::$cache[$schoolCode]=['connectionString'=>(string)$row['ConnectionString']];
    }
}
