<?php
declare(strict_types=1);
namespace App\Service;
use App\Core\Database;
final class DeviceService{
 public static function parent(array $d,int $parentId):void{if(empty($d['fcmToken']))return;$key=(string)($d['deviceId']??hash('sha256',(string)$d['fcmToken']));$platform=strtolower((string)($d['platform']??'android'));$sql='UPDATE AppParentDevice SET FcmToken=?,Platform=?,AppVersion=?,IsActive=1,UpdatedAt=SYSUTCDATETIME() WHERE AppParentID=? AND DeviceKey=?;IF @@ROWCOUNT=0 INSERT INTO AppParentDevice(AppParentID,DeviceKey,FcmToken,Platform,AppVersion) VALUES(?,?,?,?,?)';Database::connection()->prepare($sql)->execute([(string)$d['fcmToken'],$platform,$d['appVersion']??null,$parentId,$key,$parentId,$key,(string)$d['fcmToken'],$platform,$d['appVersion']??null]);}
 public static function teacher(array $d,string|int $userId):void{if(empty($d['fcmToken']))return;$id=(string)$userId;$key=(string)($d['deviceId']??hash('sha256',(string)$d['fcmToken']));$platform=strtolower((string)($d['platform']??'android'));$sql='UPDATE AppTeacherDevice SET FcmToken=?,Platform=?,AppVersion=?,IsActive=1,UpdatedAt=SYSUTCDATETIME() WHERE ApiUserID=? AND DeviceKey=?;IF @@ROWCOUNT=0 INSERT INTO AppTeacherDevice(ApiUserID,DeviceKey,FcmToken,Platform,AppVersion) VALUES(?,?,?,?,?)';Database::connection()->prepare($sql)->execute([(string)$d['fcmToken'],$platform,$d['appVersion']??null,$id,$key,$id,$key,(string)$d['fcmToken'],$platform,$d['appVersion']??null]);}
}
