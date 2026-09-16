<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Env,Request,Response,Validator};
use App\Service\ParentService;

final class PaymentController {
    public function create(Request $r,array $p):never{
        $d=$r->json();Validator::required($d,['studentId','amount']);$student=Validator::id($d['studentId'],'studentId');ParentService::student($p,$student);$amount=$d['amount'];if(!is_numeric($amount)||(float)$amount<=0)Response::error('amount must be positive.',422,'VALIDATION_ERROR');
        $provider=Env::get('PAYMENT_PROVIDER');if(!$provider)Response::error('Payment gateway is not configured.',503,'PAYMENT_GATEWAY_NOT_CONFIGURED');
        $id=self::uuid();$reference=isset($d['reference'])?substr((string)$d['reference'],0,200):null;$st=Database::connection()->prepare('INSERT INTO AppPayment(AppPaymentID,AppParentID,StudentID,Amount,Provider,Status,RequestReference) VALUES(?,?,?,?,?,?,?)');$st->execute([$id,$p['AppParentID'],$student,round((float)$amount,2),$provider,'created',$reference]);
        Response::success(['paymentId'=>$id,'provider'=>$provider,'amount'=>round((float)$amount,2),'currency'=>'INR','status'=>'created'],201);
    }
    public function verify(Request $r,array $p):never{
        $d=$r->json();Validator::required($d,['paymentId','providerPaymentId','signature']);$secret=Env::get('PAYMENT_WEBHOOK_SECRET');if(!$secret)Response::error('Payment verification is not configured.',503,'PAYMENT_GATEWAY_NOT_CONFIGURED');
        $expected=hash_hmac('sha256',(string)$d['paymentId'].'|'.(string)$d['providerPaymentId'],$secret);if(!hash_equals($expected,(string)$d['signature']))Response::error('Invalid payment signature.',422,'PAYMENT_VERIFICATION_FAILED');
        $st=Database::connection()->prepare("UPDATE AppPayment SET ProviderPaymentID=?,Status='paid',UpdatedAt=SYSUTCDATETIME() WHERE AppPaymentID=? AND AppParentID=? AND Status<>'paid'");$st->execute([(string)$d['providerPaymentId'],(string)$d['paymentId'],$p['AppParentID']]);if(!$st->rowCount())Response::error('Payment not found or already processed.',404,'PAYMENT_NOT_FOUND');Response::success(['paymentId'=>$d['paymentId'],'status'=>'paid']);
    }
    public function webhook(Request $r):never{
        $d=$r->json();Validator::required($d,['schoolCode','paymentId','providerPaymentId','status','signature']);try{Database::useSchoolCode(strtoupper(trim((string)$d['schoolCode'])));}catch(\DomainException){Response::error('Unknown school code.',404,'SCHOOL_NOT_FOUND');}$secret=Env::get('PAYMENT_WEBHOOK_SECRET');if(!$secret)Response::error('Webhook is not configured.',503,'PAYMENT_GATEWAY_NOT_CONFIGURED');
        $payload=(string)$d['paymentId'].'|'.(string)$d['providerPaymentId'].'|'.(string)$d['status'];if(!hash_equals(hash_hmac('sha256',$payload,$secret),(string)$d['signature']))Response::error('Invalid webhook signature.',401,'INVALID_SIGNATURE');
        $allowed=['created','authorized','paid','failed','refunded'];if(!in_array($d['status'],$allowed,true))Response::error('Unsupported payment status.',422,'VALIDATION_ERROR');
        $st=Database::connection()->prepare('UPDATE AppPayment SET ProviderPaymentID=?,Status=?,UpdatedAt=SYSUTCDATETIME() WHERE AppPaymentID=?');$st->execute([(string)$d['providerPaymentId'],(string)$d['status'],(string)$d['paymentId']]);Response::success(['received'=>true]);
    }
    public function status(Request $r,array $p,string $payment):never{
        $st=Database::connection()->prepare('SELECT TOP 1 AppPaymentID paymentId,StudentID studentId,CAST(Amount AS float) amount,Currency currency,Provider provider,ProviderOrderID providerOrderId,ProviderPaymentID providerPaymentId,Status status,CreatedAt createdAt,UpdatedAt updatedAt FROM AppPayment WHERE AppPaymentID=? AND AppParentID=?');$st->execute([$payment,$p['AppParentID']]);$row=$st->fetch();if(!$row)Response::error('Payment not found.',404,'PAYMENT_NOT_FOUND');Response::success(['payment'=>$row]);
    }
    private static function uuid():string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
