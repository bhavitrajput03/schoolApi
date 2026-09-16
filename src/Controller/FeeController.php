<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\{LegacyMapper,ParentService};

final class FeeController {
    public function summary(Request $r,array $p,int $student):never{
        ParentService::student($p,$student);$fees=$this->feesFor($student);$receipts=$this->receiptsFor($student);
        $due=0.0;$discount=0.0;foreach($fees as $x){$due+=LegacyMapper::money(LegacyMapper::value($x,['NetFee','Amount','FeeAmount','TotalAmount']));$discount+=LegacyMapper::money(LegacyMapper::value($x,['Discount','MDisc','DiscountAmount']));}
        $paid=0.0;foreach($receipts as $x)$paid+=LegacyMapper::money(LegacyMapper::value($x,['ReceiveAmount','PaidAmount','Amount','NetAmount']));
        Response::success(['studentId'=>$student,'totalDue'=>round($due,2),'paid'=>round($paid,2),'discount'=>round($discount,2),'balance'=>round(max(0,$due-$discount-$paid),2),'currency'=>'INR']);
    }
    public function list(Request $r,array $p,int $student):never{ParentService::student($p,$student);Response::success(['studentId'=>$student,'fees'=>$this->feesFor($student)]);}
    public function detail(Request $r,array $p,int $student,int $fee):never{
        ParentService::student($p,$student);$st=Database::connection()->prepare('SELECT TOP 1 * FROM StudentFees WHERE StudentID=? AND StudentFeesID=?');$st->execute([$student,$fee]);$row=$st->fetch();if(!$row)Response::error('Fee record not found.',404,'FEE_NOT_FOUND');
        $items=Database::connection()->prepare('SELECT * FROM StudentFeesItem WHERE StudentID=? AND StudentFeesID=? ORDER BY StudentFeesItemID');$items->execute([$student,$fee]);Response::success(['fee'=>$row,'items'=>$items->fetchAll()]);
    }
    public function account(Request $r,array $p,int $student):never{ParentService::student($p,$student);Response::success(['studentId'=>$student,'dues'=>$this->feesFor($student),'receipts'=>$this->receiptsFor($student)]);}
    public function payments(Request $r,array $p,int $student):never{ParentService::student($p,$student);$st=Database::connection()->prepare('SELECT AppPaymentID id,Amount amount,Currency currency,Provider provider,ProviderOrderID providerOrderId,ProviderPaymentID providerPaymentId,Status status,CreatedAt createdAt,UpdatedAt updatedAt FROM AppPayment WHERE AppParentID=? AND StudentID=? ORDER BY CreatedAt DESC');$st->execute([$p['AppParentID'],$student]);Response::success(['payments'=>$st->fetchAll()]);}
    public function receipts(Request $r,array $p,int $student):never{ParentService::student($p,$student);Response::success(['receipts'=>$this->receiptsFor($student)]);}
    public function receipt(Request $r,array $p,int $receipt):never{
        $db=Database::connection();$st=$db->prepare("SELECT TOP 1 'app' source,ar.AppReceiptID id,ar.StudentID studentId,ar.ReceiptNumber receiptNumber,CAST(ar.Amount AS float) amount,ar.ReceiptDate receiptDate,ar.PaymentMode paymentMode,ar.PdfPath pdfPath FROM AppReceipt ar JOIN AppParentStudent l ON l.StudentID=ar.StudentID WHERE ar.AppReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();
        if(!$row){$st=$db->prepare("SELECT TOP 1 'legacy' source,fr.* FROM FeeReceipt fr JOIN AppParentStudent l ON l.StudentID=fr.StudentID WHERE fr.FeeReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();}
        if(!$row)Response::error('Receipt not found.',404,'RECEIPT_NOT_FOUND');Response::success(['receipt'=>$row]);
    }
    public function download(Request $r,array $p,int $receipt):never{
        $st=Database::connection()->prepare('SELECT TOP 1 ar.ReceiptNumber,ar.PdfPath FROM AppReceipt ar JOIN AppParentStudent l ON l.StudentID=ar.StudentID WHERE ar.AppReceiptID=? AND l.AppParentID=?');$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();
        $base=realpath(dirname(__DIR__,2).'/storage/receipts');$file=$row&&$base?realpath($base.DIRECTORY_SEPARATOR.ltrim((string)$row['PdfPath'],'/\\')):false;
        if(!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file))Response::error('Receipt PDF not found.',404,'RECEIPT_PDF_NOT_FOUND');
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]/','',(string)$row['ReceiptNumber']).'.pdf"');header('Content-Length: '.filesize($file));readfile($file);exit;
    }
    private function feesFor(int $student):array{$st=Database::connection()->prepare('SELECT * FROM StudentFees WHERE StudentID=? ORDER BY StudentFeesID DESC');$st->execute([$student]);return$st->fetchAll();}
    private function receiptsFor(int $student):array{$legacy=Database::connection()->prepare("SELECT 'legacy' source,* FROM FeeReceipt WHERE StudentID=? ORDER BY FeeReceiptID DESC");$legacy->execute([$student]);$app=Database::connection()->prepare("SELECT 'app' source,AppReceiptID,AppPaymentID,StudentID,ReceiptNumber,Amount,ReceiptDate,PaymentMode,PdfPath FROM AppReceipt WHERE StudentID=? ORDER BY ReceiptDate DESC");$app->execute([$student]);return array_merge($app->fetchAll(),$legacy->fetchAll());}
}
