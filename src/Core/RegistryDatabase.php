<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class RegistryDatabase {
    private static ?PDO $connection=null;
    public static function connection():PDO{
        if(self::$connection)return self::$connection;
        if(!extension_loaded('pdo_sqlsrv'))throw new \RuntimeException('PHP extension pdo_sqlsrv is not enabled.');
        $connectionString=Env::get('ADMIN_DB_CONNECTION_STRING');
        if(!$connectionString)throw new \RuntimeException('ADMIN_DB_CONNECTION_STRING is not configured.');
        [$dsn,$user,$password]=SqlServerConnectionString::pdo($connectionString);
        self::$connection=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::SQLSRV_ATTR_ENCODING=>PDO::SQLSRV_ENCODING_UTF8]);return self::$connection;
    }
}
