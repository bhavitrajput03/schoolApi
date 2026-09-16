<?php
declare(strict_types=1);
$base='{{baseUrl}}';$auth=[['key'=>'Authorization','value'=>'Bearer {{accessToken}}','type'=>'text']];
$item=function(string $name,string $method,string $url,?array $body=null,bool $protected=true,array $tests=[])use($auth):array{
    $request=['method'=>$method,'header'=>$protected?$auth:[],'url'=>['raw'=>$url,'host'=>[$url]]];
    if($body!==null){$request['header'][]=['key'=>'Content-Type','value'=>'application/json','type'=>'text'];$request['body']=['mode'=>'raw','raw'=>json_encode($body,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),'options'=>['raw'=>['language'=>'json']]];}
    $scripts=["pm.test('Success status',()=>pm.expect(pm.response.code).to.be.below(500));"];
    foreach($tests as $test)$scripts[]=$test;
    return['name'=>$name,'request'=>$request,'event'=>[['listen'=>'test','script'=>['type'=>'text/javascript','exec'=>$scripts]]]];
};
$folders=[];$add=function(string $folder,array $request)use(&$folders):void{if(!isset($folders[$folder]))$folders[$folder]=[];$folders[$folder][]=$request;};
$add('Authentication',$item('Parent Login','POST',"$base/api/auth/login",['schoolCode'=>'{{schoolCode}}','parentCode'=>'{{parentCode}}','password'=>'{{password}}'],false,["let j=pm.response.json(); if(j.success){pm.environment.set('accessToken',j.data.auth.accessToken);pm.environment.set('refreshToken',j.data.auth.refreshToken);}"]));
$add('Authentication',$item('Refresh Token','POST',"$base/api/auth/refresh-token",['refreshToken'=>'{{refreshToken}}'],false,["let j=pm.response.json(); if(j.success){pm.environment.set('accessToken',j.data.auth.accessToken);pm.environment.set('refreshToken',j.data.auth.refreshToken);}"]));
$add('Authentication',$item('Logout','POST',"$base/api/auth/logout",[]));
$defs=[
['Parent & Students','Profile','GET','/api/parent/profile'],['Parent & Students','Students','GET','/api/parent/students'],['Parent & Students','Student Profile','GET','/api/students/{{studentId}}'],
['Dashboard','Student Dashboard','GET','/api/students/{{studentId}}/dashboard'],
['Attendance','Attendance Summary','GET','/api/students/{{studentId}}/attendance/summary?from={{fromDate}}&to={{toDate}}'],['Attendance','Attendance History','GET','/api/students/{{studentId}}/attendance?from={{fromDate}}&to={{toDate}}'],['Attendance','Attendance Calendar','GET','/api/students/{{studentId}}/attendance/calendar?month={{month}}'],
['Homework','Homework List','GET','/api/students/{{studentId}}/homework'],['Homework','Homework Detail','GET','/api/homework/{{homeworkId}}'],['Homework','Homework Subjects','GET','/api/students/{{studentId}}/homework/subjects'],
['Notices','Notice List','GET','/api/students/{{studentId}}/notices'],['Notices','Notice Detail','GET','/api/notices/{{noticeId}}'],['Notices','Mark Notice Read','POST','/api/notices/{{noticeId}}/read'],
['Teachers','Class Teachers','GET','/api/students/{{studentId}}/class-teachers'],['Teachers','Subject Teachers','GET','/api/students/{{studentId}}/subject-teachers'],['Teachers','Teacher Profile','GET','/api/teachers/{{teacherId}}'],
['Fees','Fee Summary','GET','/api/students/{{studentId}}/fees/summary'],['Fees','Fee Details','GET','/api/students/{{studentId}}/fees'],['Fees','Individual Fee','GET','/api/students/{{studentId}}/fees/{{feeId}}'],['Fees','Fee Account','GET','/api/students/{{studentId}}/fee-account'],
['Payments','Payment Status','GET','/api/payments/{{paymentId}}/status'],['Payments','Payment History','GET','/api/students/{{studentId}}/payments'],
['Receipts','Receipt History','GET','/api/students/{{studentId}}/receipts'],['Receipts','Receipt Detail','GET','/api/receipts/{{receiptId}}'],['Receipts','Download Receipt','GET','/api/receipts/{{receiptId}}/download'],
['Notifications','Notification List','GET','/api/notifications'],['Notifications','Unread Count','GET','/api/notifications/unread-count'],['Notifications','Mark Notification Read','POST','/api/notifications/{{notificationId}}/read'],['Notifications','Mark All Read','POST','/api/notifications/read-all'],['Notifications','Remove Device','DELETE','/api/devices/{{deviceId}}'],
['School & Settings','School','GET','/api/school'],['School & Settings','Academic Session','GET','/api/school/academic-session'],['School & Settings','App Config','GET','/api/app/config',false],['School & Settings','Settings','GET','/api/settings']];
foreach($defs as $d){$protected=$d[4]??true;$add($d[0],$item($d[1],$d[2],$base.$d[3],null,$protected));}
$add('Payments',$item('Create Order','POST',"$base/api/payments/create-order",['studentId'=>'{{studentId}}','amount'=>3500,'reference'=>'April 2026']));
$add('Payments',$item('Verify Payment','POST',"$base/api/payments/verify",['paymentId'=>'{{paymentId}}','providerPaymentId'=>'{{providerPaymentId}}','signature'=>'{{paymentSignature}}']));
$add('Payments',$item('Payment Webhook','POST',"$base/api/payments/webhook",['schoolCode'=>'{{schoolCode}}','paymentId'=>'{{paymentId}}','providerPaymentId'=>'{{providerPaymentId}}','status'=>'paid','signature'=>'{{webhookSignature}}'],false));
$add('Notifications',$item('Register Device','POST',"$base/api/devices/register",['deviceId'=>'android-device-key','fcmToken'=>'replace-me','platform'=>'android','appVersion'=>'1.0.0']));
$add('School & Settings',$item('Update Settings','PUT',"$base/api/settings",['attendanceNotifications'=>true,'feeNotifications'=>true,'homeworkNotifications'=>true,'noticeNotifications'=>true,'language'=>'en']));
$collection=['info'=>['_postman_id'=>'979ce995-160a-4f2b-a8f0-57df40fc0126','name'=>'ET Parent API','schema'=>'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'],'item'=>[]];
foreach($folders as $name=>$requests)$collection['item'][]=['name'=>$name,'item'=>$requests];
file_put_contents(dirname(__DIR__).'/postman/ET-Parent-API.postman_collection.json',json_encode($collection,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
$env=['id'=>'a7a08cb7-89e4-4dce-9321-f168ccd696b1','name'=>'ET Parent Live','values'=>[],'_postman_variable_scope'=>'environment','_postman_exported_at'=>gmdate('c'),'_postman_exported_using'=>'ET API Builder'];
foreach(['baseUrl'=>'https://api.eyetab.in','schoolCode'=>'B','parentCode'=>'PARENT007','password'=>'','accessToken'=>'','refreshToken'=>'','studentId'=>'14687','fromDate'=>'2026-09-01','toDate'=>'2026-09-30','month'=>'2026-09','homeworkId'=>'1','noticeId'=>'1','teacherId'=>'1','feeId'=>'1','paymentId'=>'','providerPaymentId'=>'','paymentSignature'=>'','webhookSignature'=>'','receiptId'=>'1','notificationId'=>'1','deviceId'=>'1'] as $k=>$v)$env['values'][]=['key'=>$k,'value'=>$v,'enabled'=>true];
file_put_contents(dirname(__DIR__).'/postman/ET-Parent-Live.postman_environment.json',json_encode($env,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo"Parent Postman files generated.\n";
