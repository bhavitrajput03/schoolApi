<?php
declare(strict_types=1);
namespace App\Core;
use App\Controller\{AttendanceController,AuthController,AuthGatewayController,ChatController,ExamController,FeeController,HomeworkController,MarksController,NoticeController,NotificationController,ParentAppController,ParentAttendanceController,ParentAuthController,ParentController,ParentTeacherController,PaymentController,TeacherChatController,TeacherContentController,TeacherController};
final class App {
    public static function run(): void {
        self::headers();$r=new Request();
        if($r->method()==='OPTIONS'){http_response_code(204);exit;}
        $routes=[
            ['POST','#^/api/auth/login$#',[AuthGatewayController::class,'login'],false],
            ['POST','#^/api/auth/refresh-token$#',[ParentAuthController::class,'refresh'],false],
            ['POST','#^/api/auth/logout$#',[AuthGatewayController::class,'logout'],false],
            ['GET','#^/api/auth/me$#',[AuthController::class,'me'],true],
            ['POST','#^/api/auth/change-password$#',[AuthController::class,'changePassword'],true],
            ['GET','#^/api/teacher/dashboard$#',[TeacherController::class,'dashboard'],true],
            ['GET','#^/api/teacher/classes$#',[TeacherController::class,'classes'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections$#',[TeacherController::class,'sections'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections/(\d+)/subjects$#',[TeacherController::class,'subjects'],true],
            ['GET','#^/api/teacher/classes/(\d+)/sections/(\d+)/students$#',[TeacherController::class,'students'],true],
            ['GET','#^/api/exams/terms$#',[ExamController::class,'terms'],true],
            ['GET','#^/api/marks/max-marks$#',[MarksController::class,'maxMarks'],true],
            ['POST','#^/api/marks/max-marks$#',[MarksController::class,'saveMaxMarks'],true],
            ['GET','#^/api/marks/subject-wise/students$#',[MarksController::class,'subjectStudents'],true],
            ['POST','#^/api/marks/subject-wise$#',[MarksController::class,'saveSubjectWise'],true],
            ['GET','#^/api/marks/student-wise/(\d+)$#',[MarksController::class,'studentWise'],true],
            ['POST','#^/api/marks/student-wise$#',[MarksController::class,'saveStudentWise'],true],
            ['GET','#^/api/attendance/students$#',[AttendanceController::class,'students'],true],
            ['POST','#^/api/attendance$#',[AttendanceController::class,'save'],true],
            ['POST','#^/api/teacher/notices$#',[TeacherContentController::class,'createNotice'],true],
            ['POST','#^/api/teacher/homework$#',[TeacherContentController::class,'createHomework'],true],
            ['GET','#^/api/teacher/homework$#',[TeacherContentController::class,'myHomework'],true],
            ['GET','#^/api/teacher/students/(\d+)/homework$#',[TeacherContentController::class,'studentHomework'],true],
            ['GET','#^/api/teacher/chat/conversations$#',[TeacherChatController::class,'inbox'],true],
            ['GET','#^/api/teacher/chat/conversations/([^/]+)/messages$#',[TeacherChatController::class,'messages'],true],
            ['POST','#^/api/teacher/chat/conversations/([^/]+)/messages$#',[TeacherChatController::class,'reply'],true],
            ['POST','#^/api/teacher/chat/conversations/([^/]+)/read$#',[TeacherChatController::class,'read'],true],
            ['POST','#^/api/teacher/devices/register$#',[TeacherController::class,'registerDevice'],true],

            ['GET','#^/api/parent/profile$#',[ParentController::class,'profile'],'parent'],
            ['GET','#^/api/parent/students$#',[ParentController::class,'students'],'parent'],
            ['GET','#^/api/students/(\d+)$#',[ParentController::class,'student'],'parent'],
            ['GET','#^/api/students/(\d+)/dashboard$#',[ParentController::class,'dashboard'],'parent'],
            ['GET','#^/api/students/(\d+)/attendance/summary$#',[ParentAttendanceController::class,'summary'],'parent'],
            ['GET','#^/api/students/(\d+)/attendance$#',[ParentAttendanceController::class,'history'],'parent'],
            ['GET','#^/api/students/(\d+)/attendance/calendar$#',[ParentAttendanceController::class,'calendar'],'parent'],
            ['GET','#^/api/students/(\d+)/homework$#',[HomeworkController::class,'list'],'parent'],
            ['GET','#^/api/homework/(\d+)$#',[HomeworkController::class,'detail'],'parent'],
            ['GET','#^/api/students/(\d+)/homework/subjects$#',[HomeworkController::class,'subjects'],'parent'],
            ['GET','#^/api/students/(\d+)/notices$#',[NoticeController::class,'list'],'parent'],
            ['GET','#^/api/notices/(\d+)$#',[NoticeController::class,'detail'],'parent'],
            ['POST','#^/api/notices/(\d+)/read$#',[NoticeController::class,'read'],'parent'],
            ['GET','#^/api/students/(\d+)/class-teachers$#',[ParentTeacherController::class,'classTeachers'],'parent'],
            ['GET','#^/api/students/(\d+)/subject-teachers$#',[ParentTeacherController::class,'subjectTeachers'],'parent'],
            ['GET','#^/api/teachers/(\d+|[0-9a-fA-F-]{36})$#',[ParentTeacherController::class,'teacher'],'parent'],
            ['GET','#^/api/chat/contacts$#',[ChatController::class,'contacts'],'parent'],
            ['GET','#^/api/chat/conversations$#',[ChatController::class,'inbox'],'parent'],
            ['GET','#^/api/chat/conversations/([^/]+)$#',[ChatController::class,'history'],'parent'],
            ['GET','#^/api/chat/conversations/([^/]+)/messages$#',[ChatController::class,'history'],'parent'],
            ['POST','#^/api/chat/messages$#',[ChatController::class,'send'],'parent'],
            ['POST','#^/api/chat/attachments$#',[ChatController::class,'attachment'],'parent'],
            ['POST','#^/api/chat/conversations/([^/]+)/read$#',[ChatController::class,'read'],'parent'],
            ['GET','#^/api/students?/(\d+)/fees?/summary$#',[FeeController::class,'summary'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee[-_]?summary$#',[FeeController::class,'summary'],'parent'],
            ['GET','#^/api/students?/(\d+)/fees?$#',[FeeController::class,'list'],'parent'],
            ['GET','#^/api/students?/(\d+)/fees?/(\d+)$#',[FeeController::class,'detail'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee[-_]?account$#',[FeeController::class,'account'],'parent'],
            ['GET','#^/api/students?/(\d+)/fees?/account$#',[FeeController::class,'account'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee[-_]?statement$#',[FeeController::class,'account'],'parent'],
            ['GET','#^/api/students?/(\d+)/statement$#',[FeeController::class,'account'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee[-_]?account/download$#',[FeeController::class,'accountDownload'],'parent'],
            ['GET','#^/api/students?/(\d+)/fees?/account/download$#',[FeeController::class,'accountDownload'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee[-_]?statement/download$#',[FeeController::class,'accountDownload'],'parent'],
            ['GET','#^/api/students?/(\d+)/statement/download$#',[FeeController::class,'accountDownload'],'parent'],
            ['POST','#^/api/payments/create-order$#',[PaymentController::class,'create'],'parent'],
            ['POST','#^/api/payments/verify$#',[PaymentController::class,'verify'],'parent'],
            ['POST','#^/api/payments/webhook$#',[PaymentController::class,'webhook'],false],
            ['GET','#^/api/payments/(\d+)/status$#',[PaymentController::class,'status'],'parent'],
            ['GET','#^/api/students?/(\d+)/payments$#',[FeeController::class,'payments'],'parent'],
            ['GET','#^/api/students?/(\d+)/receipts?$#',[FeeController::class,'receipts'],'parent'],
            ['GET','#^/api/students?/(\d+)/fee-?receipts?$#',[FeeController::class,'receipts'],'parent'],
            ['GET','#^/api/receipts/(\d+)$#',[FeeController::class,'receipt'],'parent'],
            ['GET','#^/api/receipts/(\d+)/download$#',[FeeController::class,'download'],'parent'],
            ['GET','#^/api/notifications$#',[NotificationController::class,'list'],'parent'],
            ['GET','#^/api/notifications/unread-count$#',[NotificationController::class,'unread'],'parent'],
            ['POST','#^/api/notifications/(\d+)/read$#',[NotificationController::class,'read'],'parent'],
            ['POST','#^/api/notifications/read-all$#',[NotificationController::class,'readAll'],'parent'],
            ['POST','#^/api/devices/register$#',[NotificationController::class,'registerDevice'],'parent'],
            ['DELETE','#^/api/devices/(\d+)$#',[NotificationController::class,'removeDevice'],'parent'],
            ['GET','#^/api/school$#',[ParentAppController::class,'school'],'parent'],
            ['GET','#^/api/school/academic-session$#',[ParentAppController::class,'session'],'parent'],
            ['GET','#^/api/app/config$#',[ParentAppController::class,'config'],false],
            ['GET','#^/api/settings$#',[ParentAppController::class,'settings'],'parent'],
            ['PUT','#^/api/settings$#',[ParentAppController::class,'updateSettings'],'parent'],
        ];
        foreach($routes as [$verb,$pattern,$handler,$protected])if($verb===$r->method()&&preg_match($pattern,$r->path(),$matches)){
            array_shift($matches);$user=$protected==='parent'?ParentAuth::user($r):($protected?Auth::user($r):null);$params=array_map(static fn(string $value):int|string=>ctype_digit($value)?(int)$value:$value,$matches);(new $handler[0]())->{$handler[1]}($r,$user,...$params);return;
        }
        Response::error('Endpoint not found.',404,'NOT_FOUND');
    }
    private static function headers(): void {
        header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('Cache-Control: no-store');
        $origin=$_SERVER['HTTP_ORIGIN']??'';$allowed=array_filter(array_map('trim',explode(',',Env::get('APP_ALLOWED_ORIGINS',''))));
        if($origin!==''&&in_array($origin,$allowed,true)){header("Access-Control-Allow-Origin: $origin");header('Vary: Origin');header('Access-Control-Allow-Headers: Authorization, Content-Type');header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');}
    }
}
