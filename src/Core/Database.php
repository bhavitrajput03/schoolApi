<?php
declare(strict_types=1);
namespace App\Core;
use PDO;
final class Database {
    private static ?PDO $connection=null;
    private static ?string $schoolCode=null;
    private static ?array $config=null;
    public static function useSchoolCode(string $schoolCode):void{
        $schoolCode=strtoupper(trim($schoolCode));
        if($schoolCode===''||!preg_match('/^[A-Z0-9_-]{1,50}$/',$schoolCode))throw new \DomainException('Invalid school code.');
        if(self::$schoolCode===$schoolCode&&self::$config!==null)return;
        self::$config=SchoolRegistry::find($schoolCode);
        if(self::$config===null)throw new \DomainException('Unknown school code.');
        self::$schoolCode=$schoolCode;self::$connection=null;
    }
    public static function schoolCode():string{self::ensureSchoolSelected();return(string)self::$schoolCode;}
    public static function connection():PDO{
        if(self::$connection)return self::$connection;
        if(!extension_loaded('pdo_sqlsrv'))throw new \RuntimeException('PHP extension pdo_sqlsrv is not enabled.');
        self::ensureSchoolSelected();$c=self::$config;
        [$dsn,$username,$password]=SqlServerConnectionString::pdo((string)$c['connectionString']);
        self::$connection=new PDO($dsn,$username,$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::SQLSRV_ATTR_ENCODING=>PDO::SQLSRV_ENCODING_UTF8,
        ]);TenantSchema::ensure(self::$connection);return self::$connection;
    }
    private static function ensureSchoolSelected():void{
        if(self::$config!==null)return;$default=Env::get('DEFAULT_SCHOOL_CODE');
        if($default===null||trim($default)==='')throw new \DomainException('School context is required.');
        self::useSchoolCode($default);
    }
}
