<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Database {
    private static ?PDO $connection=null;
    public static function connection(): PDO {
        if (self::$connection) return self::$connection;
        if (!extension_loaded('pdo_sqlsrv')) throw new \RuntimeException('PHP extension pdo_sqlsrv is not enabled.');
        $server=Env::get('DB_SERVER','localhost\\SQLEXPRESS'); $db=Env::get('DB_DATABASE','tmpgolden');
        $encrypt=Env::bool('DB_ENCRYPT')?'1':'0'; $trust=Env::bool('DB_TRUST_SERVER_CERTIFICATE',true)?'1':'0';
        $dsn="sqlsrv:Server=$server;Database=$db;Encrypt=$encrypt;TrustServerCertificate=$trust";
        self::$connection=new PDO($dsn,Env::get('DB_USERNAME',''),Env::get('DB_PASSWORD',''),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::SQLSRV_ATTR_ENCODING=>PDO::SQLSRV_ENCODING_UTF8,
        ]);
        return self::$connection;
    }
}
