<?php
declare(strict_types=1);
namespace App\Controller;
use App\Core\{Database,Request,Response};
final class ExamController {
    public function terms(Request $r,array $u):never{Response::success(Database::connection()->query("SELECT t.CbseExamTermID id,t.CbseExamTerm name,o.CBSEExamTermOptionID optionId,o.CBSEExamTermOption optionName,o.Wt weightage FROM CBSEExamTerm t JOIN CBSEExamTermOption o ON o.CBSEExamTermID=t.CbseExamTermID ORDER BY t.CbseExamTermID,o.CBSEExamTermOptionID")->fetchAll());}
}

