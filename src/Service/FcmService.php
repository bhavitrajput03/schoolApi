<?php
declare(strict_types=1);
namespace App\Service;

use App\Core\Env;

final class FcmService {
    private static ?array $oauth=null;

    public static function notice(array $tokens,int $noticeId,string $title,string $body):array{
        return self::send($tokens,'notice',['noticeId'=>(string)$noticeId],$title,$body);
    }

    public static function homework(array $tokens,int $homeworkId,string $title,string $body):array{
        return self::send($tokens,'homework',['homeworkId'=>(string)$homeworkId],$title,$body);
    }

    public static function chat(array $tokens,int $conversationId,int $studentId,string $title,string $body):array{
        return self::send($tokens,'chat',['conversationId'=>(string)$conversationId,'studentId'=>(string)$studentId],$title,$body);
    }

    private static function send(array $tokens,string $type,array $data,string $title,string $body):array{
        $title=mb_substr($title,0,200);$body=mb_substr($body,0,1000);
        $tokens=array_values(array_unique(array_filter(array_map('strval',$tokens))));
        if(!$tokens)return['configured'=>self::configured(),'attempted'=>0,'sent'=>0,'failed'=>0];
        try{$access=self::accessToken();}catch(\Throwable $error){error_log('FCM configuration/token error: '.$error->getMessage());return['configured'=>false,'attempted'=>count($tokens),'sent'=>0,'failed'=>count($tokens)];}
        $project=self::credentials()['project_id'];$sent=0;$failed=0;
        foreach($tokens as $token){
            $payload=['message'=>['token'=>$token,'notification'=>['title'=>$title,'body'=>$body],'data'=>array_merge(['type'=>$type],$data),'android'=>['priority'=>'high'],'apns'=>['headers'=>['apns-priority'=>'10']]]];
            [$status,$response]=self::postJson("https://fcm.googleapis.com/v1/projects/{$project}/messages:send",$payload,['Authorization: Bearer '.$access]);
            if($status>=200&&$status<300)$sent++;else{$failed++;error_log("FCM {$type} failed ({$status}): ".substr($response,0,500));}
        }
        return['configured'=>true,'attempted'=>count($tokens),'sent'=>$sent,'failed'=>$failed];
    }

    public static function configured():bool{$file=Env::get('FIREBASE_SERVICE_ACCOUNT_FILE');return is_string($file)&&trim($file)!==''&&is_readable(trim($file));}

    private static function accessToken():string{
        if(self::$oauth&&self::$oauth['expiresAt']>time()+60)return self::$oauth['token'];
        $c=self::credentials();$now=time();$header=self::base64Url(json_encode(['alg'=>'RS256','typ'=>'JWT'],JSON_THROW_ON_ERROR));
        $claims=self::base64Url(json_encode(['iss'=>$c['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging','aud'=>'https://oauth2.googleapis.com/token','iat'=>$now,'exp'=>$now+3600],JSON_THROW_ON_ERROR));
        $input=$header.'.'.$claims;$signature='';
        if(!openssl_sign($input,$signature,$c['private_key'],OPENSSL_ALGO_SHA256))throw new \RuntimeException('Could not sign Firebase JWT.');
        $jwt=$input.'.'.self::base64Url($signature);
        $curl=curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);
        $raw=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        if($raw===false||$status<200||$status>=300)throw new \RuntimeException('Firebase OAuth failed: '.($error!==''?$error:(string)$raw));
        $json=json_decode((string)$raw,true,32,JSON_THROW_ON_ERROR);if(empty($json['access_token']))throw new \RuntimeException('Firebase OAuth response has no access token.');
        self::$oauth=['token'=>(string)$json['access_token'],'expiresAt'=>$now+(int)($json['expires_in']??3600)];return self::$oauth['token'];
    }

    private static function credentials():array{
        $file=trim((string)Env::get('FIREBASE_SERVICE_ACCOUNT_FILE',''));
        if($file===''||!is_readable($file))throw new \RuntimeException('FIREBASE_SERVICE_ACCOUNT_FILE is missing or unreadable.');
        $json=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);
        foreach(['project_id','client_email','private_key'] as $key)if(empty($json[$key]))throw new \RuntimeException("Firebase service account is missing {$key}.");
        return$json;
    }

    private static function postJson(string $url,array $payload,array $headers=[]):array{
        $curl=curl_init($url);$headers[]='Content-Type: application/json';
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>$headers]);
        $response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
        return[$status,$response===false?$error:(string)$response];
    }

    private static function base64Url(string $value):string{return rtrim(strtr(base64_encode($value),'+/','-_'),'=');}
}
