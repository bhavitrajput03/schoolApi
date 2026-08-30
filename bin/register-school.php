<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
use App\Core\{RegistryDatabase,SqlServerConnectionString};
if(PHP_SAPI!=='cli'||$argc!==3){fwrite(STDERR,"Usage: php bin/register-school.php SCHOOL_CODE FULL_SQL_CONNECTION_STRING\n");exit(1);}
[, $code,$connectionString]=$argv;$code=strtoupper(trim($code));
if(!preg_match('/^[A-Z0-9_-]{1,50}$/',$code)||trim($connectionString)===''){fwrite(STDERR,"Invalid or missing school connection values.\n");exit(1);}
SqlServerConnectionString::pdo($connectionString);
$db=RegistryDatabase::connection();$db->beginTransaction();
try{$update=$db->prepare('UPDATE dbo.SchoolConnection SET ConnectionString=? WHERE SchoolCode=?');$update->execute([trim($connectionString),$code]);if($update->rowCount()===0)$db->prepare('INSERT INTO dbo.SchoolConnection(SchoolCode,ConnectionString) VALUES(?,?)')->execute([$code,trim($connectionString)]);$db->commit();}catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw$e;}echo"School SQL connection saved successfully.\n";
