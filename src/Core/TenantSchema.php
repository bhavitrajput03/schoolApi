<?php
declare(strict_types=1);
namespace App\Core;

use PDO;

/** Creates only the small API authentication tables that are absent in a tenant. */
final class TenantSchema
{
    private static array $ready=[];

    public static function ensure(PDO $db):void
    {
        $database=(string)$db->query('SELECT DB_NAME()')->fetchColumn();
        if(isset(self::$ready[$database]))return;

        $st=$db->query("SELECT TYPE_NAME(user_type_id) FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.SchoolTeacher') AND name=N'ApiUserID'");
        $type=strtolower((string)$st->fetchColumn());
        if(!in_array($type,['uniqueidentifier','int','bigint'],true)){
            throw new \RuntimeException('SchoolTeacher.ApiUserID must be uniqueidentifier, int or bigint.');
        }

        try{
            $db->exec("IF OBJECT_ID(N'dbo.ApiLoginAttempt',N'U') IS NULL BEGIN CREATE TABLE dbo.ApiLoginAttempt(ApiLoginAttemptID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_ApiLoginAttempt PRIMARY KEY,AttemptKey char(64) NOT NULL,AttemptedAt datetime2 NOT NULL);CREATE INDEX IX_ApiLoginAttempt_KeyTime ON dbo.ApiLoginAttempt(AttemptKey,AttemptedAt);END");
            $db->exec("IF OBJECT_ID(N'dbo.ApiAuthToken',N'U') IS NULL CREATE TABLE dbo.ApiAuthToken(ApiAuthTokenID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_ApiAuthToken PRIMARY KEY,ApiUserID {$type} NOT NULL,TokenHash char(64) NOT NULL CONSTRAINT UQ_ApiAuthToken_TokenHash UNIQUE,CreatedAt datetime2 NOT NULL CONSTRAINT DF_ApiAuthToken_CreatedAt DEFAULT SYSUTCDATETIME(),ExpiresAt datetime2 NOT NULL,LastUsedAt datetime2 NULL,RevokedAt datetime2 NULL,IpAddress varchar(45) NULL,UserAgent nvarchar(500) NULL,CONSTRAINT FK_ApiAuthToken_SchoolTeacher FOREIGN KEY(ApiUserID) REFERENCES dbo.SchoolTeacher(ApiUserID))");
        }catch(\PDOException $error){
            throw new \RuntimeException('Tenant API authentication tables are missing and the configured SQL login cannot create them.',0,$error);
        }

        $tokenType=strtolower((string)$db->query("SELECT TYPE_NAME(user_type_id) FROM sys.columns WHERE object_id=OBJECT_ID(N'dbo.ApiAuthToken') AND name=N'ApiUserID'")->fetchColumn());
        if($tokenType!==$type)throw new \RuntimeException("ApiAuthToken.ApiUserID type {$tokenType} does not match SchoolTeacher.ApiUserID type {$type}.");
        self::$ready[$database]=true;
    }
}
