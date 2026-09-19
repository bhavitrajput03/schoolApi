<?php
declare(strict_types=1);
$file=dirname(__DIR__).'/postman/ET-Teacher-API.postman_collection.json';
$collection=json_decode((string)file_get_contents($file),true,64,JSON_THROW_ON_ERROR);

foreach($collection['variable'] as &$variable){$existing[$variable['key']]=true;}
unset($variable);
foreach(['fcmToken'=>'','loginDeviceId'=>'android-device-key','noticeId'=>'','homeworkId'=>'','contentDate'=>date('Y-m-d'),'contentDueDate'=>date('Y-m-d',strtotime('+1 day'))] as $key=>$value){
    if(!isset($existing[$key]))$collection['variable'][]=['key'=>$key,'value'=>$value,'type'=>'string'];
}

foreach($collection['item'] as &$folder){
    if(($folder['name']??'')==='01 Authentication'){
        foreach($folder['item'] as &$request){
            if(($request['name']??'')==='Login')$request['request']['body']['raw']="{\n  \"schoolCode\": \"{{schoolCode}}\",\n  \"username\": \"{{username}}\",\n  \"password\": \"{{password}}\",\n  \"fcmToken\": \"{{fcmToken}}\",\n  \"deviceId\": \"{{loginDeviceId}}\",\n  \"platform\": \"android\",\n  \"appVersion\": \"1.0.0\"\n}";
        }
        unset($request);
    }
}
unset($folder);

$authHeader=[['key'=>'Content-Type','value'=>'application/json']];
$make=static function(string $name,string $path,array $body,string $captureKey,string $responseKey)use($authHeader):array{return[
    'name'=>$name,
    'event'=>[['listen'=>'test','script'=>['type'=>'text/javascript','exec'=>["const json=pm.response.json(); if(json.success){pm.collectionVariables.set('{$captureKey}',json.data.{$responseKey}.id);}"]]]],
    'request'=>['method'=>'POST','header'=>$authHeader,'body'=>['mode'=>'raw','raw'=>json_encode($body,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),'options'=>['raw'=>['language'=>'json']]],'url'=>['raw'=>'{{baseUrl}}'.$path,'host'=>['{{baseUrl}}'],'path'=>array_values(array_filter(explode('/',$path)))]]
];};

$requests=[];
$requests[]=$make('WRITE - Publish Section Notice','/api/teacher/notices',['noticeType'=>'academic','targetType'=>'section','classId'=>'{{classId}}','sectionId'=>'{{sectionId}}','title'=>'Tomorrow class update','body'=>'Please bring the required notebook.','attachmentUrl'=>null],'noticeId','notice');
$requests[]=$make('WRITE - Publish Student Notice','/api/teacher/notices',['noticeType'=>'urgent','targetType'=>'student','classId'=>'{{classId}}','sectionId'=>'{{sectionId}}','studentId'=>'{{studentId}}','title'=>'Parent attention required','body'=>'Please contact the class teacher.','attachmentUrl'=>null],'noticeId','notice');
$requests[]=$make('WRITE - Publish Class Notice','/api/teacher/notices',['noticeType'=>'exam','targetType'=>'class','classId'=>'{{classId}}','title'=>'Exam schedule','body'=>'The exam schedule has been published.','attachmentUrl'=>null],'noticeId','notice');
$requests[]=$make('WRITE - Publish School Notice (Admin)','/api/teacher/notices',['noticeType'=>'holiday','targetType'=>'school','title'=>'School holiday','body'=>'The school will remain closed tomorrow.','attachmentUrl'=>null],'noticeId','notice');
$requests[]=$make('WRITE - Assign Homework','/api/teacher/homework',['classId'=>'{{classId}}','sectionId'=>'{{sectionId}}','subjectId'=>'{{subjectId}}','title'=>'Chapter revision','description'=>'Complete questions 1 to 10.','homeworkDate'=>'{{contentDate}}','dueDate'=>'{{contentDueDate}}','attachmentUrl'=>null],'homeworkId','homework');

$collection['item']=array_values(array_filter($collection['item'],static fn(array $folder):bool=>($folder['name']??'')!=='06 Notices & Homework'));
$collection['item'][]=['name'=>'06 Notices & Homework','item'=>$requests];
file_put_contents($file,json_encode($collection,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo "Teacher notice and homework requests added.\n";
