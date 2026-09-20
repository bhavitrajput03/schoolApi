<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
use App\Service\{LegacyMapper,ParentService,SchoolService,SimplePdf};

final class FeeController {
    public function summary(Request $r,array $p,int $student):never{
        $studentRow=ParentService::student($p,$student);$info=$this->studentData($studentRow,$student);$dues=$this->normalizedFees($student,$info);$receipts=$this->normalizedReceipts($student,$info);$summary=$this->summaryData($dues,$receipts);
        Response::success(array_merge(['studentId'=>$student,'student_id'=>$student,'StudentID'=>$student,'student'=>$info,'Student'=>$info,'studentDetails'=>$info,'student_details'=>$info,'studentInfo'=>$info,'student_info'=>$info],$summary,['summary'=>$summary,'Summary'=>$summary]));
    }
    public function list(Request $r,array $p,int $student):never{
        $studentRow=ParentService::student($p,$student);$info=$this->studentData($studentRow,$student);$fees=$this->normalizedFees($student,$info);$receipts=$this->normalizedReceipts($student,$info);$summary=$this->summaryData($fees,$receipts);
        Response::success(array_merge(['studentId'=>$student,'student_id'=>$student,'StudentID'=>$student,'student'=>$info,'Student'=>$info,'studentDetails'=>$info,'student_details'=>$info,'studentInfo'=>$info,'student_info'=>$info],$summary,['summary'=>$summary,'Summary'=>$summary,'fees'=>$fees,'fee_list'=>$fees,'items'=>$fees,'list'=>$fees,'dues'=>$fees,'records'=>$fees]));
    }
    public function detail(Request $r,array $p,int $student,int $fee):never{
        ParentService::student($p,$student);$st=Database::connection()->prepare('SELECT TOP 1 * FROM StudentFees WHERE StudentID=? AND StudentFeesID=?');$st->execute([$student,$fee]);$row=$st->fetch();if(!$row)Response::error('Fee record not found.',404,'FEE_NOT_FOUND');
        $items=Database::connection()->prepare('SELECT * FROM StudentFeesItem WHERE StudentID=? AND StudentFeesID=? ORDER BY StudentFeesItemID');$items->execute([$student,$fee]);Response::success(['fee'=>$row,'items'=>$items->fetchAll()]);
    }
    public function account(Request $r,array $p,int $student):never{
        $session=SchoolService::currentSession();$studentRow=ParentService::student($p,$student);$info=$this->studentData($studentRow,$student);$dues=$this->normalizedFees($student,$info);$receipts=$this->normalizedReceipts($student,$info);$summary=$this->summaryData($dues,$receipts);$transactions=$this->ledger($dues,$receipts,$info);
        $download="/api/students/{$student}/fee-account/download";
        Response::success(array_merge(
            [
                'studentId'=>$student,'student_id'=>$student,'StudentID'=>$student,'StudentId'=>$student,
                'student'=>$info,'Student'=>$info,'studentDetails'=>$info,'student_details'=>$info,'studentInfo'=>$info,'student_info'=>$info,
                'academicSession'=>$session,'academic_session'=>$session,'session'=>$session,
                'session_name'=>$session['name']??'','sessionName'=>$session['name']??'','academic_year'=>$session['name']??'','academicYear'=>$session['name']??''
            ],
            $summary,
            [
                'summary'=>$summary,'Summary'=>$summary,
                'transactions'=>$transactions,'Transactions'=>$transactions,'items'=>$transactions,'Items'=>$transactions,'ledger'=>$transactions,'Ledger'=>$transactions,
                'feeAccount'=>$transactions,'fee_account'=>$transactions,'FeeAccount'=>$transactions,'statement'=>$transactions,'statements'=>$transactions,'Statement'=>$transactions,
                'records'=>$transactions,'list'=>$transactions,'entries'=>$transactions,'history'=>$transactions,
                'dues'=>$dues,'Dues'=>$dues,'fees'=>$dues,'Fees'=>$dues,'receipts'=>$receipts,'Receipts'=>$receipts,
                'download_url'=>$download,'downloadUrl'=>$download,'pdf_url'=>$download,'pdfUrl'=>$download
            ]
        ));
    }
    public function accountDownload(Request $r,array $p,int $student):never{$studentRow=ParentService::student($p,$student);$info=$this->studentData($studentRow,$student);$dues=$this->normalizedFees($student,$info);$receipts=$this->normalizedReceipts($student,$info);SimplePdf::feeStatement($info,$this->summaryData($dues,$receipts),$dues,$receipts,"fee-account-{$student}.pdf");}
    public function payments(Request $r,array $p,int $student):never{ParentService::student($p,$student);$st=Database::connection()->prepare('SELECT AppPaymentID id,Amount amount,Currency currency,Provider provider,ProviderOrderID providerOrderId,ProviderPaymentID providerPaymentId,Status status,CreatedAt createdAt,UpdatedAt updatedAt FROM AppPayment WHERE AppParentID=? AND StudentID=? ORDER BY CreatedAt DESC');$st->execute([$p['AppParentID'],$student]);Response::success(['payments'=>$st->fetchAll()]);}
    public function receipts(Request $r,array $p,int $student):never{
        $studentRow=ParentService::student($p,$student);$info=$this->studentData($studentRow,$student);$receipts=$this->normalizedReceipts($student,$info);$dues=$this->normalizedFees($student,$info);$summary=$this->summaryData($dues,$receipts);
        Response::success(array_merge(
            ['studentId'=>$student,'student_id'=>$student,'StudentID'=>$student,'student'=>$info,'Student'=>$info,'studentDetails'=>$info,'student_details'=>$info,'studentInfo'=>$info,'student_info'=>$info],$summary,
            [
                'summary'=>$summary,'Summary'=>$summary,
                'receipts'=>$receipts,'Receipts'=>$receipts,'receipt_list'=>$receipts,'items'=>$receipts,'list'=>$receipts,'records'=>$receipts,'data'=>$receipts,'transactions'=>$receipts
            ]
        ));
    }
    public function receipt(Request $r,array $p,int $receipt):never{
        $db=Database::connection();$st=$db->prepare("SELECT TOP 1 'app' source,ar.AppReceiptID id,ar.StudentID studentId,ar.ReceiptNumber receiptNumber,CAST(ar.Amount AS float) amount,ar.ReceiptDate receiptDate,ar.PaymentMode paymentMode,ar.PdfPath pdfPath FROM AppReceipt ar JOIN AppParentStudent l ON l.StudentID=ar.StudentID WHERE ar.AppReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();
        if(!$row){$st=$db->prepare("SELECT TOP 1 'legacy' source,fr.FeeReceiptID id,fr.* FROM FeeReceipt fr JOIN AppParentStudent l ON l.StudentID=fr.StudentID WHERE fr.FeeReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();}
        if(!$row)Response::error('Receipt not found.',404,'RECEIPT_NOT_FOUND');
        $sId=(int)LegacyMapper::value($row,['studentId','StudentID'],0);$sRow=$sId>0?ParentService::student($p,$sId):[];$info=$this->studentData($sRow,$sId);
        $norm=$this->normalizeReceipt($row,$info);
        Response::success(array_merge($norm,['receipt'=>$norm,'data'=>$norm,'item'=>$norm,'details'=>$norm]));
    }
    public function download(Request $r,array $p,int $receipt):never{
        $db=Database::connection();
        $st=$db->prepare('SELECT TOP 1 ar.ReceiptNumber,ar.PdfPath FROM AppReceipt ar JOIN AppParentStudent l ON l.StudentID=ar.StudentID WHERE ar.AppReceiptID=? AND l.AppParentID=?');$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();
        if(!$row){$st=$db->prepare('SELECT TOP 1 fr.VoucherNo ReceiptNumber,NULL PdfPath FROM FeeReceipt fr JOIN AppParentStudent l ON l.StudentID=fr.StudentID WHERE fr.FeeReceiptID=? AND l.AppParentID=?');$st->execute([$receipt,$p['AppParentID']]);$row=$st->fetch();}
        $base=realpath(dirname(__DIR__,2).'/storage/receipts');$file=$row&&!empty($row['PdfPath'])&&$base?realpath($base.DIRECTORY_SEPARATOR.ltrim((string)$row['PdfPath'],'/\\')):false;
        if(!$file||!str_starts_with($file,$base.DIRECTORY_SEPARATOR)||!is_file($file)){$receiptRow=$this->receiptRow($p,$receipt);if(!$receiptRow)Response::error('Receipt not found.',404,'RECEIPT_NOT_FOUND');$normalized=$this->normalizeReceipt($receiptRow);$studentRow=ParentService::student($p,(int)$normalized['studentId']);SimplePdf::feeReceipt($normalized,$this->studentData($studentRow,(int)$normalized['studentId']),'receipt-'.$normalized['id'].'.pdf');}
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9_-]/','',(string)($row['ReceiptNumber']??'receipt')).'.pdf"');header('Content-Length: '.filesize($file));readfile($file);exit;
    }
    private function feesFor(int $student):array{try{$st=Database::connection()->prepare('SELECT * FROM StudentFees WHERE StudentID=? ORDER BY StudentFeesID DESC');$st->execute([$student]);return$st->fetchAll();}catch(\PDOException){return[];}}
    private function receiptsFor(int $student):array{$all=[];try{$legacy=Database::connection()->prepare("SELECT 'legacy' source,FeeReceiptID id,* FROM FeeReceipt WHERE StudentID=? ORDER BY FeeReceiptID DESC");$legacy->execute([$student]);$all=$legacy->fetchAll();}catch(\PDOException){}try{$app=Database::connection()->prepare("SELECT 'app' source,AppReceiptID id,AppPaymentID,StudentID,ReceiptNumber,Amount,ReceiptDate,PaymentMode,PdfPath FROM AppReceipt WHERE StudentID=? ORDER BY ReceiptDate DESC");$app->execute([$student]);$all=array_merge($app->fetchAll(),$all);}catch(\PDOException){}return$all;}
    private function receiptRow(array $p,int $receipt):array|false{$db=Database::connection();try{$st=$db->prepare("SELECT TOP 1 'app' source,ar.AppReceiptID id,ar.StudentID studentId,ar.ReceiptNumber receiptNumber,ar.Amount amount,ar.ReceiptDate receiptDate,ar.PaymentMode paymentMode FROM AppReceipt ar JOIN AppParentStudent l ON l.StudentID=ar.StudentID WHERE ar.AppReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);if($x=$st->fetch())return$x;}catch(\PDOException){}try{$st=$db->prepare("SELECT TOP 1 'legacy' source,fr.FeeReceiptID id,fr.* FROM FeeReceipt fr JOIN AppParentStudent l ON l.StudentID=fr.StudentID WHERE fr.FeeReceiptID=? AND l.AppParentID=?");$st->execute([$receipt,$p['AppParentID']]);return$st->fetch();}catch(\PDOException){return false;}}
    private function normalizedFees(int $student,array $studentInfo=[]):array{return array_map(fn($x)=>$this->normalizeFee($x,$studentInfo),$this->feesFor($student));}
    private function normalizedReceipts(int $student,array $studentInfo=[]):array{return array_map(fn($x)=>$this->normalizeReceipt($x,$studentInfo),$this->receiptsFor($student));}
    private function normalizeFee(array $x,array $studentInfo=[]):array{
        $id=(int)LegacyMapper::value($x,['StudentFeesID','fee_id','feeId','id'],0);$date=LegacyMapper::date(LegacyMapper::value($x,['DueDate','FeeDate','Date','CreatedAt']))??'';$description=(string)LegacyMapper::value($x,['FeeName','Title','Description','Particular'],'School fee');$amount=LegacyMapper::money(LegacyMapper::value($x,['NetFee','Amount','FeeAmount','TotalAmount']));$discount=LegacyMapper::money(LegacyMapper::value($x,['Discount','MDisc','DiscountAmount']));$paid=LegacyMapper::money(LegacyMapper::value($x,['PaidAmount','ReceiveAmount','ReceivedAmount'],0));$balance=round(max(0,$amount-$discount-$paid),2);
        $item = [
            'StudentFeesID'=>$id,'FeeReceiptID'=>0,'VoucherNo'=>'','ReceiveAmount'=>0.0,'Dated'=>$date,'PaymentMode'=>'Due','M_FeeMonths'=>$description,
            'StudentID'=>$studentInfo['studentId']??0,'StudentName'=>$studentInfo['name']??'','AdmissionNo'=>$studentInfo['admissionNo']??'','ClassName'=>$studentInfo['className']??'','SectionName'=>$studentInfo['sectionName']??'','FatherName'=>$studentInfo['fatherName']??'',
            'FeeName'=>$description,'Particular'=>$description,'Particulars'=>$description,'Title'=>$description,'Description'=>$description,
            'FeeAmount'=>$amount,'NetFee'=>$amount,'TotalAmount'=>$amount,'PaidAmount'=>$paid,'DiscountAmount'=>$discount,'Discount'=>$discount,'BalanceAmount'=>$balance,'Balance'=>$balance,'DueDate'=>$date,'FeeDate'=>$date,
            'id'=>$id,'fee_id'=>$id,'feeId'=>$id,'title'=>$description,'description'=>$description,'particulars'=>$description,'remarks'=>$description,'fee_head'=>$description,'feeHead'=>$description,
            'date'=>$date,'due_date'=>$date,'dueDate'=>$date,'created_at'=>$date,'createdAt'=>$date,
            'amount'=>$amount,'fee_amount'=>$amount,'feeAmount'=>$amount,'total_amount'=>$amount,'totalAmount'=>$amount,'net_fee'=>$amount,'netFee'=>$amount,'due'=>$amount,'due_amount'=>$amount,'dueAmount'=>$amount,
            'discount'=>$discount,'discount_amount'=>$discount,'discountAmount'=>$discount,
            'paid'=>$paid,'paid_amount'=>$paid,'paidAmount'=>$paid,
            'balance'=>$balance,'balance_amount'=>$balance,'balanceAmount'=>$balance,
            'status'=>$balance<=0?'paid':'pending','type'=>'due','transaction_type'=>'due','transactionType'=>'due',
            'Type'=>'due','TransactionType'=>'due','is_fee'=>true,'IsFee'=>true,'is_receipt'=>false,'IsReceipt'=>false,
            'student_name'=>$studentInfo['name']??'','studentName'=>$studentInfo['name']??'',
            'class_name'=>$studentInfo['className']??'','className'=>$studentInfo['className']??'',
            'section_name'=>$studentInfo['sectionName']??'','sectionName'=>$studentInfo['sectionName']??'',
            'admission_no'=>$studentInfo['admissionNo']??'','admissionNo'=>$studentInfo['admissionNo']??'',
            'student'=>$studentInfo
        ];
        return array_merge($studentInfo, $item);
    }
    private function normalizeReceipt(array $x,array $studentInfo=[]):array{
        $id=(int)LegacyMapper::value($x,['FeeReceiptID','AppReceiptID','receipt_id','receiptId','id'],0);$studentId=(int)LegacyMapper::value($x,['studentId','StudentID'],0);$number=(string)LegacyMapper::value($x,['receiptNumber','ReceiptNumber','VoucherNo','VoucherStr'],(string)$id);$date=LegacyMapper::date(LegacyMapper::value($x,['receiptDate','ReceiptDate','Dated','Date']))??'';$amount=LegacyMapper::money(LegacyMapper::value($x,['amount','Amount','ReceiveAmount','PaidAmount','NetAmount']));$rawBalance=LegacyMapper::value($x,['Balance','BalanceAmount','DueAmount','RemainAmount','RemainingAmount']);$balance=LegacyMapper::money($rawBalance);$mode=(string)LegacyMapper::value($x,['paymentMode','PaymentMode','ModeName','PayMode'],'-');$months=(string)LegacyMapper::value($x,['M_FeeMonths','FeeMonths','Months','Month','particulars','title','description'],$date);$download='/api/receipts/'.$id.'/download';$desc='Receipt '.$number;
        $item = [
            'FeeReceiptID'=>$id,'StudentFeesID'=>0,'VoucherNo'=>$number,'ReceiveAmount'=>$amount,'Dated'=>$date,'PaymentMode'=>$mode,'M_FeeMonths'=>$months,
            'StudentID'=>$studentId,'StudentName'=>$studentInfo['name']??'','AdmissionNo'=>$studentInfo['admissionNo']??'','ClassName'=>$studentInfo['className']??'','SectionName'=>$studentInfo['sectionName']??'','FatherName'=>$studentInfo['fatherName']??'',
            'FeeName'=>$desc,'Particular'=>$desc,'Particulars'=>$desc,'Title'=>$desc,'Description'=>$desc,
            'FeeAmount'=>$amount,'NetFee'=>$amount,'TotalAmount'=>$amount,'PaidAmount'=>$amount,'DiscountAmount'=>0.0,'Discount'=>0.0,'BalanceAmount'=>$balance,'Balance'=>$balance,'DueDate'=>$date,'FeeDate'=>$date,
            'id'=>$id,'receipt_id'=>$id,'receiptId'=>$id,'student_id'=>$studentId,'studentId'=>$studentId,
            'receipt_number'=>$number,'receiptNumber'=>$number,'receipt_no'=>$number,'receiptNo'=>$number,'voucher_number'=>$number,'voucherNumber'=>$number,'voucher_no'=>$number,'voucherNo'=>$number,
            'title'=>$desc,'description'=>$desc,'particulars'=>$desc,'remarks'=>$desc,'fee_head'=>'Fee Receipt','feeHead'=>'Fee Receipt','head'=>'Fee Receipt',
            'date'=>$date,'receipt_date'=>$date,'receiptDate'=>$date,'payment_date'=>$date,'paymentDate'=>$date,'created_at'=>$date,'createdAt'=>$date,'due_date'=>$date,'dueDate'=>$date,
            'amount'=>$amount,'paid_amount'=>$amount,'paidAmount'=>$amount,'total_amount'=>$amount,'totalAmount'=>$amount,'received_amount'=>$amount,'receivedAmount'=>$amount,'fee_amount'=>$amount,'feeAmount'=>$amount,'net_amount'=>$amount,'netAmount'=>$amount,
            'due'=>0.0,'due_amount'=>0.0,'dueAmount'=>0.0,'discount'=>0.0,'discount_amount'=>0.0,'discountAmount'=>0.0,
            'balance'=>$balance,'balance_amount'=>$balance,'balanceAmount'=>$balance,'balanceKnown'=>$rawBalance!==null&&is_numeric($rawBalance),
            'payment_mode'=>$mode,'paymentMode'=>$mode,'payment_method'=>$mode,'paymentMethod'=>$mode,'pay_mode'=>$mode,'mode'=>$mode,
            'status'=>'paid','receipt_status'=>'paid','receiptStatus'=>'paid','type'=>'receipt','transaction_type'=>'payment','transactionType'=>'payment',
            'Type'=>'receipt','TransactionType'=>'payment','is_fee'=>false,'IsFee'=>false,'is_receipt'=>true,'IsReceipt'=>true,
            'source'=>(string)LegacyMapper::value($x,['source'],'legacy'),
            'download_url'=>$download,'downloadUrl'=>$download,'pdf_url'=>$download,'pdfUrl'=>$download,'file_url'=>$download,'fileUrl'=>$download,'download_path'=>$download,'downloadPath'=>$download,'url'=>$download,
            'is_cancelled'=>false,'cancelled'=>false,'can_download'=>true,'downloadable'=>true,'is_downloadable'=>true,
            'currency'=>'INR','currency_symbol'=>'₹',
            'student_name'=>$studentInfo['name']??'','studentName'=>$studentInfo['name']??'',
            'class_name'=>$studentInfo['className']??'','className'=>$studentInfo['className']??'',
            'section_name'=>$studentInfo['sectionName']??'','sectionName'=>$studentInfo['sectionName']??'',
            'admission_no'=>$studentInfo['admissionNo']??'','admissionNo'=>$studentInfo['admissionNo']??'',
            'student'=>$studentInfo
        ];
        return array_merge($studentInfo, $item);
    }
    private function summaryData(array $dues,array $receipts):array{
        $billed=array_sum(array_column($dues,'amount'));$discount=array_sum(array_column($dues,'discount'));$paid=array_sum(array_column($receipts,'amount'));$sorted=$receipts;usort($sorted,static fn($a,$b)=>strcmp((string)($b['date']??''),(string)($a['date']??'')));$knownBalance=null;foreach($sorted as $receipt)if($receipt['balanceKnown']??false){$knownBalance=(float)$receipt['balance'];break;}$balance=$knownBalance??max(0,$billed-$discount-$paid);$total=max($billed,$paid+$balance+$discount);
        return [
            'totalDue'=>round($total,2),'total_due'=>round($total,2),'TotalDue'=>round($total,2),
            'totalFee'=>round($total,2),'total_fee'=>round($total,2),'TotalFee'=>round($total,2),
            'totalAmount'=>round($total,2),'total_amount'=>round($total,2),'TotalAmount'=>round($total,2),
            'feeAmount'=>round($total,2),'fee_amount'=>round($total,2),'FeeAmount'=>round($total,2),
            'netFee'=>round($total,2),'net_fee'=>round($total,2),'NetFee'=>round($total,2),
            'discount'=>round($discount,2),'discount_amount'=>round($discount,2),'discountAmount'=>round($discount,2),'total_discount'=>round($discount,2),'totalDiscount'=>round($discount,2),'Discount'=>round($discount,2),'DiscountAmount'=>round($discount,2),'TotalDiscount'=>round($discount,2),
            'paid'=>round($paid,2),'paid_amount'=>round($paid,2),'paidAmount'=>round($paid,2),'total_paid'=>round($paid,2),'totalPaid'=>round($paid,2),'Paid'=>round($paid,2),'PaidAmount'=>round($paid,2),'TotalPaid'=>round($paid,2),'ReceiveAmount'=>round($paid,2),
            'balance'=>round($balance,2),'balance_amount'=>round($balance,2),'balanceAmount'=>round($balance,2),'total_balance'=>round($balance,2),'totalBalance'=>round($balance,2),'due'=>round($balance,2),'due_amount'=>round($balance,2),'dueAmount'=>round($balance,2),'pending'=>round($balance,2),'pending_amount'=>round($balance,2),'pendingAmount'=>round($balance,2),'Balance'=>round($balance,2),'BalanceAmount'=>round($balance,2),'TotalBalance'=>round($balance,2),'DueAmount'=>round($balance,2),'PendingAmount'=>round($balance,2),
            'currency'=>'INR','currency_symbol'=>'₹','currencySymbol'=>'₹','Currency'=>'INR','CurrencySymbol'=>'₹'
        ];
    }
    public function ledger(array $dues,array $receipts,array $studentInfo=[]):array{
        $totalPaid=array_sum(array_column($receipts,'amount'));
        if(empty($dues)&&$totalPaid>0){
            $oldestDate=!empty($receipts)?(string)end($receipts)['date']:date('Y-04-01');
            $dues=[$this->normalizeFee([
                'StudentFeesID'=>1,
                'FeeName'=>'Academic Session Fee',
                'Title'=>'Academic Session Fee',
                'Description'=>'Academic Session Fee',
                'Particular'=>'Academic Session Fee',
                'DueDate'=>$oldestDate,
                'NetFee'=>$totalPaid,
                'Amount'=>$totalPaid,
                'PaidAmount'=>$totalPaid,
                'Discount'=>0.0
            ],$studentInfo)];
        }
        $all=array_merge($dues,$receipts);
        usort($all,static fn($a,$b)=>strcmp((string)($a['date']??''),(string)($b['date']??'')));
        $running=0.0;
        foreach($all as &$item){
            if(($item['type']??'')==='due'){
                $running+=((float)($item['amount']??0)-(float)($item['discount']??0));
                $item['balance']=round($running,2);
                $item['balanceAmount']=round($running,2);
                $item['balance_amount']=round($running,2);
                $item['Balance']=round($running,2);
                $item['BalanceAmount']=round($running,2);
            }else{
                $running=max(0.0,$running-(float)($item['amount']??0));
                $item['balance']=round($running,2);
                $item['balanceAmount']=round($running,2);
                $item['balance_amount']=round($running,2);
                $item['Balance']=round($running,2);
                $item['BalanceAmount']=round($running,2);
            }
        }
        unset($item);
        usort($all,static fn($a,$b)=>strcmp((string)($b['date']??''),(string)($a['date']??'')));
        return $all;
    }
    private function studentData(array $x,int $id):array{
        try{
            $db=Database::connection();$st=$db->prepare('SELECT TOP 1 s.StudentID,s.StudentName,s.AdmissionNo,s.FatherName,c.Classmaster className,se.SectionName sectionName FROM Student s LEFT JOIN StudentSession ss ON ss.StudentID=s.StudentID AND ss.IsLeave=0 LEFT JOIN ClassMaster c ON c.ClassmasterID=ss.ClassID LEFT JOIN SectionMaster se ON se.SectionMasterID=ss.SectionID WHERE s.StudentID=?');$st->execute([$id]);$fetched=$st->fetch()?:[];$x=array_merge($x,$fetched);
        }catch(\PDOException){}
        $name=(string)LegacyMapper::value($x,['StudentName','Name','studentName','FirstName'],'Student');
        $adm=(string)LegacyMapper::value($x,['AdmissionNo','AdmissionNumber','admissionNo','ScholarNo'],'-');
        $father=(string)LegacyMapper::value($x,['FatherName','fatherName'],'-');
        $cls=(string)LegacyMapper::value($x,['className','Classmaster','ClassMaster'],'-');
        $sec=(string)LegacyMapper::value($x,['sectionName','SectionName'],'-');
        return [
            'studentId'=>$id,'student_id'=>$id,'StudentID'=>$id,'StudentId'=>$id,
            'name'=>$name,'studentName'=>$name,'student_name'=>$name,'StudentName'=>$name,
            'admissionNo'=>$adm,'admission_no'=>$adm,'admission_number'=>$adm,'AdmissionNo'=>$adm,
            'fatherName'=>$father,'father_name'=>$father,'FatherName'=>$father,
            'className'=>$cls,'class_name'=>$cls,'ClassName'=>$cls,'Classmaster'=>$cls,
            'sectionName'=>$sec,'section_name'=>$sec,'SectionName'=>$sec
        ];
    }
}
