-- Run once in each tenant database (for example SchoolManagement) as dbo/sa.
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.AppParent',N'U') IS NULL
CREATE TABLE dbo.AppParent(
 AppParentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParent PRIMARY KEY,
 SchoolBranchID bigint NOT NULL,ParentCode varchar(100) NOT NULL,DisplayName nvarchar(150) NOT NULL,
 Mobile varchar(30) NULL,Email varchar(150) NULL,PasswordHash varchar(255) NOT NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppParent_Active DEFAULT 1,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppParent_Created DEFAULT SYSUTCDATETIME(),
 PasswordChangedAt datetime2 NULL,LastLoginAt datetime2 NULL,
 CONSTRAINT UQ_AppParent_Login UNIQUE(SchoolBranchID,ParentCode),
 CONSTRAINT FK_AppParent_School FOREIGN KEY(SchoolBranchID) REFERENCES dbo.SchoolBranch(SchoolBranchID));

IF OBJECT_ID(N'dbo.AppParentStudent',N'U') IS NULL
CREATE TABLE dbo.AppParentStudent(
 AppParentStudentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentStudent PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,Relationship varchar(30) NULL,
 IsPrimary bit NOT NULL CONSTRAINT DF_AppParentStudent_Primary DEFAULT 0,
 CONSTRAINT UQ_AppParentStudent UNIQUE(AppParentID,StudentID),
 CONSTRAINT FK_AppParentStudent_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppParentStudent_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));

IF OBJECT_ID(N'dbo.AppParentAuthToken',N'U') IS NULL
BEGIN
 CREATE TABLE dbo.AppParentAuthToken(
  AppParentAuthTokenID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentAuthToken PRIMARY KEY,
  AppParentID int NOT NULL,AccessTokenHash char(64) NOT NULL,RefreshTokenHash char(64) NOT NULL,
  AccessExpiresAt datetime2 NOT NULL,RefreshExpiresAt datetime2 NOT NULL,RevokedAt datetime2 NULL,
  LastUsedAt datetime2 NULL,CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppParentToken_Created DEFAULT SYSUTCDATETIME(),
  IpAddress varchar(45) NULL,UserAgent nvarchar(500) NULL,
  CONSTRAINT UQ_AppParentToken_Access UNIQUE(AccessTokenHash),
  CONSTRAINT UQ_AppParentToken_Refresh UNIQUE(RefreshTokenHash),
  CONSTRAINT FK_AppParentToken_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID));
 CREATE INDEX IX_AppParentToken_Refresh ON dbo.AppParentAuthToken(RefreshTokenHash,RefreshExpiresAt);
END;

IF OBJECT_ID(N'dbo.AppHomework',N'U') IS NULL
CREATE TABLE dbo.AppHomework(
 AppHomeworkID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppHomework PRIMARY KEY,
 OwnerSessionID bigint NOT NULL,ClassID bigint NOT NULL,SectionID bigint NULL,SubjectID bigint NULL,
 Title nvarchar(200) NOT NULL,Description nvarchar(max) NULL,HomeworkDate date NOT NULL,DueDate date NULL,
 AttachmentUrl nvarchar(1000) NULL,TeacherEmployeeID bigint NULL,CreatedByApiUserID varchar(64) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppHomework_Active DEFAULT 1,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppHomework_Created DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppHomework_Session FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppHomework_Class FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppHomework_Section FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID),
 CONSTRAINT FK_AppHomework_Subject FOREIGN KEY(SubjectID) REFERENCES dbo.CBSEExamSubject(CBSEExamSubjectID),
 CONSTRAINT FK_AppHomework_Employee FOREIGN KEY(TeacherEmployeeID) REFERENCES dbo.Employee(EmployeeID));

IF OBJECT_ID(N'dbo.AppClassTeacher',N'U') IS NULL
CREATE TABLE dbo.AppClassTeacher(
 AppClassTeacherID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppClassTeacher PRIMARY KEY,
 OwnerSessionID bigint NOT NULL,ClassID bigint NOT NULL,SectionID bigint NOT NULL,ApiUserID varchar(64) NOT NULL,
 TeacherRole varchar(30) NOT NULL,IsActive bit NOT NULL CONSTRAINT DF_AppClassTeacher_Active DEFAULT 1,
 CONSTRAINT CK_AppClassTeacher_Role CHECK(TeacherRole IN('class_teacher','co_class_teacher')),
 CONSTRAINT UQ_AppClassTeacher UNIQUE(OwnerSessionID,ClassID,SectionID,ApiUserID,TeacherRole),
 CONSTRAINT FK_AppClassTeacher_Session FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppClassTeacher_Class FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppClassTeacher_Section FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID));

IF OBJECT_ID(N'dbo.AppNotice',N'U') IS NULL
CREATE TABLE dbo.AppNotice(
 AppNoticeID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotice PRIMARY KEY,
 OwnerSessionID bigint NULL,NoticeType varchar(30) NOT NULL CONSTRAINT DF_AppNotice_Type DEFAULT 'general',
 TargetType varchar(20) NOT NULL CONSTRAINT DF_AppNotice_Target DEFAULT 'school',Title nvarchar(200) NOT NULL,Body nvarchar(max) NOT NULL,
 ClassID bigint NULL,SectionID bigint NULL,StudentID bigint NULL,PublishedAt datetime2 NOT NULL,
 PublishedBy nvarchar(150) NULL,CreatedByApiUserID varchar(64) NULL,AttachmentUrl nvarchar(1000) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppNotice_Active DEFAULT 1,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppNotice_Created DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppNotice_Session FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppNotice_Class FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppNotice_Section FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID),
 CONSTRAINT FK_AppNotice_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID),
 CONSTRAINT CK_AppNotice_Type CHECK(NoticeType IN('general','academic','exam','event','holiday','fee','urgent')),
 CONSTRAINT CK_AppNotice_Target CHECK(TargetType IN('school','class','section','student')));

IF COL_LENGTH(N'dbo.AppHomework',N'CreatedByApiUserID') IS NULL
 ALTER TABLE dbo.AppHomework ADD CreatedByApiUserID varchar(64) NULL;

IF COL_LENGTH(N'dbo.AppNotice',N'NoticeType') IS NULL
 ALTER TABLE dbo.AppNotice ADD NoticeType varchar(30) NOT NULL CONSTRAINT DF_AppNotice_Type_Upgrade DEFAULT 'general';
IF COL_LENGTH(N'dbo.AppNotice',N'TargetType') IS NULL
 ALTER TABLE dbo.AppNotice ADD TargetType varchar(20) NOT NULL CONSTRAINT DF_AppNotice_Target_Upgrade DEFAULT 'school';
IF COL_LENGTH(N'dbo.AppNotice',N'CreatedByApiUserID') IS NULL
 ALTER TABLE dbo.AppNotice ADD CreatedByApiUserID varchar(64) NULL;
IF COL_LENGTH(N'dbo.AppNotice',N'AttachmentUrl') IS NULL
 ALTER TABLE dbo.AppNotice ADD AttachmentUrl nvarchar(1000) NULL;

IF OBJECT_ID(N'dbo.AppNoticeRead',N'U') IS NULL
CREATE TABLE dbo.AppNoticeRead(
 AppNoticeReadID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNoticeRead PRIMARY KEY,
 AppParentID int NOT NULL,AppNoticeID int NOT NULL,ReadAt datetime2 NOT NULL CONSTRAINT DF_AppNoticeRead_Date DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppNoticeRead UNIQUE(AppParentID,AppNoticeID),
 CONSTRAINT FK_AppNoticeRead_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppNoticeRead_Notice FOREIGN KEY(AppNoticeID) REFERENCES dbo.AppNotice(AppNoticeID));

IF OBJECT_ID(N'dbo.AppNotification',N'U') IS NULL
CREATE TABLE dbo.AppNotification(
 AppNotificationID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotification PRIMARY KEY,
 AppParentID int NULL,StudentID bigint NULL,NotificationType varchar(40) NOT NULL,
 Title nvarchar(200) NOT NULL,Body nvarchar(1000) NOT NULL,RelatedType varchar(40) NULL,RelatedID varchar(100) NULL,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppNotification_Created DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppNotification_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppNotification_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));

IF OBJECT_ID(N'dbo.AppNotificationRead',N'U') IS NULL
CREATE TABLE dbo.AppNotificationRead(
 AppNotificationReadID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotificationRead PRIMARY KEY,
 AppParentID int NOT NULL,AppNotificationID int NOT NULL,ReadAt datetime2 NOT NULL CONSTRAINT DF_AppNotificationRead_Date DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppNotificationRead UNIQUE(AppParentID,AppNotificationID),
 CONSTRAINT FK_AppNotificationRead_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppNotificationRead_Notification FOREIGN KEY(AppNotificationID) REFERENCES dbo.AppNotification(AppNotificationID));

IF OBJECT_ID(N'dbo.AppParentDevice',N'U') IS NULL
CREATE TABLE dbo.AppParentDevice(
 AppParentDeviceID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentDevice PRIMARY KEY,
 AppParentID int NOT NULL,DeviceKey varchar(200) NOT NULL,FcmToken varchar(500) NOT NULL,
 Platform varchar(20) NOT NULL,AppVersion varchar(30) NULL,IsActive bit NOT NULL CONSTRAINT DF_AppDevice_Active DEFAULT 1,
 UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppDevice_Updated DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppParentDevice UNIQUE(AppParentID,DeviceKey),
 CONSTRAINT FK_AppDevice_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID));

IF OBJECT_ID(N'dbo.AppParentSetting',N'U') IS NULL
CREATE TABLE dbo.AppParentSetting(
 AppParentSettingID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentSetting PRIMARY KEY,
 AppParentID int NOT NULL CONSTRAINT UQ_AppParentSetting_Parent UNIQUE,
 AttendanceNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Attendance DEFAULT 1,
 FeeNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Fee DEFAULT 1,
 HomeworkNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Homework DEFAULT 1,
 NoticeNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Notice DEFAULT 1,
 Language varchar(10) NOT NULL CONSTRAINT DF_AppSetting_Language DEFAULT 'en',
 UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppSetting_Updated DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppSetting_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID));

IF OBJECT_ID(N'dbo.AppPayment',N'U') IS NULL
CREATE TABLE dbo.AppPayment(
 AppPaymentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppPayment PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,Amount numeric(18,2) NOT NULL,Currency char(3) NOT NULL CONSTRAINT DF_AppPayment_Currency DEFAULT 'INR',
 Provider varchar(30) NOT NULL,ProviderOrderID varchar(150) NULL,ProviderPaymentID varchar(150) NULL,
 Status varchar(30) NOT NULL CONSTRAINT DF_AppPayment_Status DEFAULT 'created',RequestReference varchar(200) NULL,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppPayment_Created DEFAULT SYSUTCDATETIME(),UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppPayment_Updated DEFAULT SYSUTCDATETIME(),
 CONSTRAINT CK_AppPayment_Amount CHECK(Amount>0),
 CONSTRAINT FK_AppPayment_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppPayment_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));

IF OBJECT_ID(N'dbo.AppReceipt',N'U') IS NULL
CREATE TABLE dbo.AppReceipt(
 AppReceiptID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppReceipt PRIMARY KEY,
 AppPaymentID int NULL,StudentID bigint NOT NULL,ReceiptNumber varchar(100) NOT NULL,
 Amount numeric(18,2) NOT NULL,ReceiptDate datetime2 NOT NULL,PaymentMode varchar(50) NULL,PdfPath nvarchar(1000) NULL,
 CONSTRAINT UQ_AppReceipt_Number UNIQUE(ReceiptNumber),
 CONSTRAINT FK_AppReceipt_Payment FOREIGN KEY(AppPaymentID) REFERENCES dbo.AppPayment(AppPaymentID),
 CONSTRAINT FK_AppReceipt_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));

IF OBJECT_ID(N'dbo.AppTeacherDevice',N'U') IS NULL
CREATE TABLE dbo.AppTeacherDevice(
 AppTeacherDeviceID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppTeacherDevice PRIMARY KEY,
 ApiUserID varchar(64) NOT NULL,DeviceKey varchar(200) NOT NULL,FcmToken varchar(500) NOT NULL,
 Platform varchar(20) NOT NULL,AppVersion varchar(30) NULL,IsActive bit NOT NULL CONSTRAINT DF_AppTeacherDevice_Active DEFAULT 1,
 UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppTeacherDevice_Updated DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppTeacherDevice UNIQUE(ApiUserID,DeviceKey));

IF OBJECT_ID(N'dbo.AppChatConversation',N'U') IS NULL
CREATE TABLE dbo.AppChatConversation(
 AppChatConversationID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatConversation PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,TeacherApiUserID varchar(64) NOT NULL,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppChatConversation_Created DEFAULT SYSUTCDATETIME(),UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppChatConversation_Updated DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppChatConversation UNIQUE(AppParentID,StudentID,TeacherApiUserID),
 CONSTRAINT FK_AppChatConversation_Parent FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent(AppParentID),
 CONSTRAINT FK_AppChatConversation_Student FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));

IF OBJECT_ID(N'dbo.AppChatMessage',N'U') IS NULL
CREATE TABLE dbo.AppChatMessage(
 AppChatMessageID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatMessage PRIMARY KEY,
 AppChatConversationID int NOT NULL,SenderType varchar(10) NOT NULL,SenderID varchar(64) NOT NULL,Message nvarchar(max) NULL,
 SentAt datetime2 NOT NULL CONSTRAINT DF_AppChatMessage_Sent DEFAULT SYSUTCDATETIME(),ReadAt datetime2 NULL,
 CONSTRAINT CK_AppChatMessage_Sender CHECK(SenderType IN('parent','teacher')),
 CONSTRAINT FK_AppChatMessage_Conversation FOREIGN KEY(AppChatConversationID) REFERENCES dbo.AppChatConversation(AppChatConversationID));

IF OBJECT_ID(N'dbo.AppChatAttachment',N'U') IS NULL
CREATE TABLE dbo.AppChatAttachment(
 AppChatAttachmentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatAttachment PRIMARY KEY,
 AppChatMessageID int NOT NULL,FileName nvarchar(255) NOT NULL,MimeType varchar(150) NOT NULL,FileUrl nvarchar(1000) NOT NULL,FileSize bigint NULL,
 CONSTRAINT FK_AppChatAttachment_Message FOREIGN KEY(AppChatMessageID) REFERENCES dbo.AppChatMessage(AppChatMessageID));

COMMIT TRANSACTION;
GO
