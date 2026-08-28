<?php
declare(strict_types=1);
namespace App\Core;
use App\Controller\{AttendanceController,AuthController,ExamController,MarksController,TeacherController};
final class App {
    public static function run(): void {
        self::headers();$r=new Request();
        if($r->method()==='OPTIONS'){http_response_code(204);exit;}
        $routes=[
            ['POST','#^/api/auth/login$#',[AuthController::class,'login'],false],
            ['POST','#^/api/auth/logout$#',[AuthController::class,'logout'],true],
            ['GET','#^/api/auth/me$#',[AuthController::class,'me'],true],
            ['POST','#^/api/auth/change-password$#',[AuthController::class,'changePassword'],true],
            ['GET','#^/api/teacher/dashboard$#',[TeacherController::class,'dashboard'],true],
            ['GET','#^/api/teacher/classes$#',[TeacherController::class,'classes'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections$#',[TeacherController::class,'sections'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections/(\d+)/subjects$#',[TeacherController::class,'subjects'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections/(\d+)/students$#',[TeacherController::class,'students'],true],
            ['GET','#^/api/exams/terms$#',[ExamController::class,'terms'],true],
            ['GET','#^/api/marks/subject-wise/students$#',[MarksController::class,'subjectStudents'],true],
            ['POST','#^/api/marks/subject-wise$#',[MarksController::class,'saveSubjectWise'],true],
            ['GET','#^/api/marks/student-wise/(\d+)$#',[MarksController::class,'studentWise'],true],
            ['POST','#^/api/marks/student-wise$#',[MarksController::class,'saveStudentWise'],true],
            ['GET','#^/api/attendance/students$#',[AttendanceController::class,'students'],true],
            ['POST','#^/api/attendance$#',[AttendanceController::class,'save'],true],
        ];
        foreach($routes as [$verb,$pattern,$handler,$protected])if($verb===$r->method()&&preg_match($pattern,$r->path(),$matches)){
            array_shift($matches);$user=$protected?Auth::user($r):null;(new $handler[0]())->{$handler[1]}($r,$user,...array_map('intval',$matches));return;
        }
        Response::error('Endpoint not found.',404,'NOT_FOUND');
    }
    private static function headers(): void {
        header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
        $origin=$_SERVER['HTTP_ORIGIN']??'';$allowed=array_filter(array_map('trim',explode(',',Env::get('APP_ALLOWED_ORIGINS',''))));
        if($origin!==''&&in_array($origin,$allowed,true)){header("Access-Control-Allow-Origin: $origin");header('Vary: Origin');header('Access-Control-Allow-Headers: Authorization, Content-Type');header('Access-Control-Allow-Methods: GET, POST, OPTIONS');}
    }
}
