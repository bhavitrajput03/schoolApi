<?php
declare(strict_types=1);
namespace App\Service;

/** Dependency-free, styled PDF writer for fee statements and receipts. */
final class SimplePdf {
    private const W=595.0;
    private const NAVY='0.035 0.145 0.290';
    private const INK='0.105 0.145 0.210';
    private const MUTED='0.390 0.440 0.510';
    private const LINE='0.850 0.875 0.910';
    private const PALE='0.950 0.970 0.995';

    public static function feeStatement(array $student,array $summary,array $dues,array $receipts,string $filename):never {
        $ledger=(new \App\Controller\FeeController())->ledger($dues,$receipts);
        usort($ledger,static fn($a,$b)=>strcmp((string)($a['date']??''),(string)($b['date']??'')));
        $rows=[];
        foreach($ledger as $item){
            $isDue=($item['type']??'')==='due';
            $rows[]=[
                self::clean($item['date']??$item['dueDate']??''),
                self::clean($item['description']??$item['title']??'Transaction'),
                $isDue?self::money($item['amount']??0):'-',
                !$isDue?self::money($item['amount']??0):'-',
                self::money($item['balance']??0)
            ];
        }
        $meta=[['Student',self::clean($student['name']??'Student')],['Student ID',self::clean($student['id']??'')],['Admission No.',self::clean($student['admissionNo']??'-')],['Generated',date('d M Y, h:i A')]];
        $total=$summary['totalFee']??$summary['totalDue']??0;
        $cards=[['Total fee',self::money($total)],['Discount',self::money($summary['discount']??0)],['Paid',self::money($summary['paid']??0)],['Balance',self::money($summary['balance']??0)]];
        self::sendDocument('Fee Account Statement',$meta,$cards,['Date','Description','Due','Paid','Balance'],$rows,'This is a system-generated fee statement. Please contact the school accounts office for any discrepancy.',$filename);
    }

    public static function feeReceipt(array $receipt,array $student,string $filename):never {
        $amount=self::money($receipt['amount']??0);
        $meta=[['Receipt No.',self::clean($receipt['receiptNumber']??$receipt['id']??'-')],['Receipt Date',self::clean($receipt['date']??'-')],['Student',self::clean($student['name']??'Student')],['Student ID',self::clean($student['id']??$receipt['studentId']??'')],['Admission No.',self::clean($student['admissionNo']??'-')],['Payment Mode',self::clean($receipt['paymentMode']??'-')]];
        self::sendDocument('Fee Payment Receipt',$meta,[['Amount received',$amount]],['Particulars','Amount'],[['Fee payment received',$amount]],'Payment received with thanks. This is a system-generated receipt and does not require a signature.',$filename);
    }

    public static function send(string $title,array $lines,string $filename):never {
        $rows=[];foreach($lines as $line)$rows[]=[self::clean(is_scalar($line)?$line:json_encode($line))];
        self::sendDocument($title,[],[],['Details'],$rows,'System-generated document.',$filename);
    }

    private static function sendDocument(string $title,array $meta,array $cards,array $headers,array $rows,string $note,string $filename):never {
        $pages=[];$page=self::newPage($title);$y=704.0;
        foreach($meta as [$label,$value]){self::text($page,46,$y,$label,9,self::MUTED);self::text($page,155,$y,$value,10,self::INK,true);$y-=20;}
        if($meta)$y-=8;
        if($cards){$count=count($cards);$gap=8.0;$cw=(503.0-($count-1)*$gap)/$count;foreach($cards as $i=>[$label,$value]){$x=46+$i*($cw+$gap);self::rect($page,$x,$y-54,$cw,54,self::PALE,self::LINE);self::text($page,$x+10,$y-17,$label,8,self::MUTED);self::text($page,$x+10,$y-39,'INR '.$value,12,self::NAVY,true);}$y-=74;}
        $widths=self::columnWidths(count($headers));self::tableHeader($page,$headers,$widths,$y);$y-=28;
        if(!$rows){self::text($page,56,$y-18,'No fee transactions found.',10,self::MUTED);$y-=42;}
        foreach($rows as $row){if($y<92){$pages[]=$page;$page=self::newPage($title.' - continued');$y=704;self::tableHeader($page,$headers,$widths,$y);$y-=28;}self::line($page,46,$y-25,549,$y-25,self::LINE);$x=46;foreach($headers as $i=>$unused){$value=self::truncate(self::clean($row[$i]??''),$widths[$i],9);$right=$i>=max(1,count($headers)-3);self::text($page,$right?$x+$widths[$i]-5:$x+5,$y-17,$value,9,self::INK,false,$right);$x+=$widths[$i];}$y-=26;}
        self::line($page,46,$y-4,549,$y-4,self::LINE);$y-=30;self::text($page,46,$y,$note,8,self::MUTED);$pages[]=$page;
        $pdf=self::build($pages);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$filename).'"');header('Content-Length: '.strlen($pdf));header('Cache-Control: private, no-store');echo $pdf;exit;
    }

    private static function newPage(string $title):string {$p='';self::rect($p,0,762,self::W,80,self::NAVY);self::text($p,46,805,'EYETAB SCHOOL ERP',10,'1 1 1',true);self::text($p,46,781,$title,18,'1 1 1',true);self::text($p,549,805,'PARENT PORTAL',8,'0.72 0.82 0.94',false,true);return $p;}
    private static function tableHeader(string &$p,array $headers,array $widths,float $y):void{self::rect($p,46,$y-28,503,28,self::NAVY);$x=46;foreach($headers as $i=>$h){$right=$i>=max(1,count($headers)-3);self::text($p,$right?$x+$widths[$i]-5:$x+5,$y-18,$h,8,'1 1 1',true,$right);$x+=$widths[$i];}}
    private static function columnWidths(int $n):array{return match($n){1=>[503.0],2=>[370.0,133.0],5=>[75.0,188.0,80.0,80.0,80.0],default=>array_fill(0,$n,503.0/max(1,$n))};}
    private static function text(string &$p,float $x,float $y,string $text,float $size,string $color,bool $bold=false,bool $right=false):void{$text=self::pdfText($text);if($right)$x-=strlen($text)*$size*0.49;$font=$bold?'F2':'F1';$p.="BT /{$font} {$size} Tf {$color} rg ".self::num($x).' '.self::num($y)." Td ({$text}) Tj ET\n";}
    private static function rect(string &$p,float $x,float $y,float $w,float $h,string $fill,string $stroke=''):void{$p.="{$fill} rg ".self::num($x).' '.self::num($y).' '.self::num($w).' '.self::num($h)." re f\n";if($stroke)$p.="{$stroke} RG 0.6 w ".self::num($x).' '.self::num($y).' '.self::num($w).' '.self::num($h)." re S\n";}
    private static function line(string &$p,float $x1,float $y1,float $x2,float $y2,string $color):void{$p.="{$color} RG 0.6 w ".self::num($x1).' '.self::num($y1).' m '.self::num($x2).' '.self::num($y2)." l S\n";}
    private static function truncate(string $s,float $width,float $size):string{$max=max(3,(int)floor(($width-10)/($size*.51)));return strlen($s)>$max?substr($s,0,$max-3).'...':$s;}
    private static function money(mixed $v):string{return number_format(is_numeric($v)?(float)$v:0,2,'.',',');}
    private static function clean(mixed $v):string{$s=trim((string)($v??''));return preg_replace('/\s+/',' ',$s)??'';}
    private static function pdfText(string $s):string{$s=preg_replace('/[^\x20-\x7E]/','?',$s)??'';return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$s);}
    private static function num(float $n):string{return rtrim(rtrim(number_format($n,2,'.',''),'0'),'.');}
    private static function build(array $pages):string {$objects=[];$pageIds=[];$next=5;foreach($pages as $_){$pageIds[]=$next;$next+=2;}$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pages).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';$objects[4]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';foreach($pages as $i=>$stream){$pid=$pageIds[$i];$cid=$pid+1;$stream.="BT /F1 8 Tf ".self::MUTED.' rg 46 35 Td (Generated by EyeTab | Page '.($i+1).' of '.count($pages).") Tj ET\n";$objects[$pid]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$cid.' 0 R >>';$objects[$cid]="<< /Length ".strlen($stream)." >>\nstream\n{$stream}endstream";}ksort($objects);$pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offset=[0];foreach($objects as $id=>$body){$offset[$id]=strlen($pdf);$pdf.="{$id} 0 obj\n{$body}\nendobj\n";}$xref=strlen($pdf);$size=max(array_keys($objects))+1;$pdf.="xref\n0 {$size}\n0000000000 65535 f \n";for($i=1;$i<$size;$i++)$pdf.=isset($offset[$i])?sprintf('%010d 00000 n ',$offset[$i])."\n":"0000000000 00000 f \n";$pdf.="trailer << /Size {$size} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";return $pdf;}
}
