-- Data-preserving migration from the original UUID/BIGINT parent schema to INT IDENTITY IDs.
-- Run as dbo/sa. The whole migration is transactional and rolls back on any error.
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF (SELECT TYPE_NAME(c.user_type_id) FROM sys.columns c WHERE c.object_id=OBJECT_ID(N'dbo.AppParent') AND c.name=N'AppParentID')='int'
BEGIN
    COMMIT TRANSACTION;
    PRINT 'Parent schema already uses INT IDs; no migration required.';
    RETURN;
END;

IF OBJECT_ID(N'dbo.AppParent_New',N'U') IS NOT NULL
BEGIN
    THROW 51000,'A previous _New migration table exists. Inspect it before retrying.',1;
END;

IF EXISTS(SELECT AppParentStudentID FROM dbo.AppParentStudent WHERE AppParentStudentID>2147483647 UNION ALL
          SELECT AppParentAuthTokenID FROM dbo.AppParentAuthToken WHERE AppParentAuthTokenID>2147483647 UNION ALL
          SELECT AppHomeworkID FROM dbo.AppHomework WHERE AppHomeworkID>2147483647 UNION ALL
          SELECT AppClassTeacherID FROM dbo.AppClassTeacher WHERE AppClassTeacherID>2147483647 UNION ALL
          SELECT AppNoticeID FROM dbo.AppNotice WHERE AppNoticeID>2147483647 UNION ALL
          SELECT AppNotificationID FROM dbo.AppNotification WHERE AppNotificationID>2147483647 UNION ALL
          SELECT AppParentDeviceID FROM dbo.AppParentDevice WHERE AppParentDeviceID>2147483647 UNION ALL
          SELECT AppReceiptID FROM dbo.AppReceipt WHERE AppReceiptID>2147483647 UNION ALL
          SELECT AppTeacherDeviceID FROM dbo.AppTeacherDevice WHERE AppTeacherDeviceID>2147483647 UNION ALL
          SELECT AppChatMessageID FROM dbo.AppChatMessage WHERE AppChatMessageID>2147483647 UNION ALL
          SELECT AppChatAttachmentID FROM dbo.AppChatAttachment WHERE AppChatAttachmentID>2147483647)
BEGIN
    THROW 51000,'At least one existing identity value is larger than INT can store.',1;
END;

CREATE TABLE #ParentMap(OldID uniqueidentifier NOT NULL PRIMARY KEY,NewID int NOT NULL UNIQUE);
CREATE TABLE #PaymentMap(OldID uniqueidentifier NOT NULL PRIMARY KEY,NewID int NOT NULL UNIQUE);
CREATE TABLE #ConversationMap(OldID uniqueidentifier NOT NULL PRIMARY KEY,NewID int NOT NULL UNIQUE);

CREATE TABLE dbo.AppParent_New(
 AppParentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParent_V2 PRIMARY KEY,
 SchoolBranchID bigint NOT NULL,ParentCode varchar(100) NOT NULL,DisplayName nvarchar(150) NOT NULL,
 Mobile varchar(30) NULL,Email varchar(150) NULL,PasswordHash varchar(255) NOT NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppParent_Active_V2 DEFAULT 1,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppParent_Created_V2 DEFAULT SYSUTCDATETIME(),
 PasswordChangedAt datetime2 NULL,LastLoginAt datetime2 NULL,
 CONSTRAINT UQ_AppParent_Login_V2 UNIQUE(SchoolBranchID,ParentCode),
 CONSTRAINT FK_AppParent_School_V2 FOREIGN KEY(SchoolBranchID) REFERENCES dbo.SchoolBranch(SchoolBranchID));

MERGE dbo.AppParent_New AS target USING dbo.AppParent AS source ON 1=0
WHEN NOT MATCHED THEN INSERT(SchoolBranchID,ParentCode,DisplayName,Mobile,Email,PasswordHash,IsActive,CreatedAt,PasswordChangedAt,LastLoginAt)
VALUES(source.SchoolBranchID,source.ParentCode,source.DisplayName,source.Mobile,source.Email,source.PasswordHash,source.IsActive,source.CreatedAt,source.PasswordChangedAt,source.LastLoginAt)
OUTPUT source.AppParentID,inserted.AppParentID INTO #ParentMap(OldID,NewID);

CREATE TABLE dbo.AppParentStudent_New(
 AppParentStudentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentStudent_V2 PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,Relationship varchar(30) NULL,IsPrimary bit NOT NULL CONSTRAINT DF_AppParentStudent_Primary_V2 DEFAULT 0,
 CONSTRAINT UQ_AppParentStudent_V2 UNIQUE(AppParentID,StudentID),
 CONSTRAINT FK_AppParentStudent_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppParentStudent_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));
SET IDENTITY_INSERT dbo.AppParentStudent_New ON;
INSERT dbo.AppParentStudent_New(AppParentStudentID,AppParentID,StudentID,Relationship,IsPrimary)
SELECT o.AppParentStudentID,m.NewID,o.StudentID,o.Relationship,o.IsPrimary FROM dbo.AppParentStudent o JOIN #ParentMap m ON m.OldID=o.AppParentID;
SET IDENTITY_INSERT dbo.AppParentStudent_New OFF;

CREATE TABLE dbo.AppParentAuthToken_New(
 AppParentAuthTokenID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentAuthToken_V2 PRIMARY KEY,
 AppParentID int NOT NULL,AccessTokenHash char(64) NOT NULL,RefreshTokenHash char(64) NOT NULL,
 AccessExpiresAt datetime2 NOT NULL,RefreshExpiresAt datetime2 NOT NULL,RevokedAt datetime2 NULL,LastUsedAt datetime2 NULL,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppParentToken_Created_V2 DEFAULT SYSUTCDATETIME(),IpAddress varchar(45) NULL,UserAgent nvarchar(500) NULL,
 CONSTRAINT UQ_AppParentToken_Access_V2 UNIQUE(AccessTokenHash),CONSTRAINT UQ_AppParentToken_Refresh_V2 UNIQUE(RefreshTokenHash),
 CONSTRAINT FK_AppParentToken_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID));
SET IDENTITY_INSERT dbo.AppParentAuthToken_New ON;
INSERT dbo.AppParentAuthToken_New(AppParentAuthTokenID,AppParentID,AccessTokenHash,RefreshTokenHash,AccessExpiresAt,RefreshExpiresAt,RevokedAt,LastUsedAt,CreatedAt,IpAddress,UserAgent)
SELECT o.AppParentAuthTokenID,m.NewID,o.AccessTokenHash,o.RefreshTokenHash,o.AccessExpiresAt,o.RefreshExpiresAt,o.RevokedAt,o.LastUsedAt,o.CreatedAt,o.IpAddress,o.UserAgent FROM dbo.AppParentAuthToken o JOIN #ParentMap m ON m.OldID=o.AppParentID;
SET IDENTITY_INSERT dbo.AppParentAuthToken_New OFF;
CREATE INDEX IX_AppParentToken_Refresh_V2 ON dbo.AppParentAuthToken_New(RefreshTokenHash,RefreshExpiresAt);

CREATE TABLE dbo.AppHomework_New(
 AppHomeworkID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppHomework_V2 PRIMARY KEY,
 OwnerSessionID bigint NOT NULL,ClassID bigint NOT NULL,SectionID bigint NULL,SubjectID bigint NULL,
 Title nvarchar(200) NOT NULL,Description nvarchar(max) NULL,HomeworkDate date NOT NULL,DueDate date NULL,
 AttachmentUrl nvarchar(1000) NULL,TeacherEmployeeID bigint NULL,CreatedByApiUserID varchar(64) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppHomework_Active_V2 DEFAULT 1,CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppHomework_Created_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppHomework_Session_V2 FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppHomework_Class_V2 FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppHomework_Section_V2 FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID),
 CONSTRAINT FK_AppHomework_Subject_V2 FOREIGN KEY(SubjectID) REFERENCES dbo.CBSEExamSubject(CBSEExamSubjectID),
 CONSTRAINT FK_AppHomework_Employee_V2 FOREIGN KEY(TeacherEmployeeID) REFERENCES dbo.Employee(EmployeeID));
SET IDENTITY_INSERT dbo.AppHomework_New ON;
INSERT dbo.AppHomework_New(AppHomeworkID,OwnerSessionID,ClassID,SectionID,SubjectID,Title,Description,HomeworkDate,DueDate,AttachmentUrl,TeacherEmployeeID,CreatedByApiUserID,IsActive,CreatedAt)
SELECT AppHomeworkID,OwnerSessionID,ClassID,SectionID,SubjectID,Title,Description,HomeworkDate,DueDate,AttachmentUrl,TeacherEmployeeID,CreatedByApiUserID,IsActive,CreatedAt FROM dbo.AppHomework;
SET IDENTITY_INSERT dbo.AppHomework_New OFF;

CREATE TABLE dbo.AppClassTeacher_New(
 AppClassTeacherID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppClassTeacher_V2 PRIMARY KEY,
 OwnerSessionID bigint NOT NULL,ClassID bigint NOT NULL,SectionID bigint NOT NULL,ApiUserID varchar(64) NOT NULL,TeacherRole varchar(30) NOT NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppClassTeacher_Active_V2 DEFAULT 1,
 CONSTRAINT CK_AppClassTeacher_Role_V2 CHECK(TeacherRole IN('class_teacher','co_class_teacher')),
 CONSTRAINT UQ_AppClassTeacher_V2 UNIQUE(OwnerSessionID,ClassID,SectionID,ApiUserID,TeacherRole),
 CONSTRAINT FK_AppClassTeacher_Session_V2 FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppClassTeacher_Class_V2 FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppClassTeacher_Section_V2 FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID));
SET IDENTITY_INSERT dbo.AppClassTeacher_New ON;
INSERT dbo.AppClassTeacher_New(AppClassTeacherID,OwnerSessionID,ClassID,SectionID,ApiUserID,TeacherRole,IsActive)
SELECT AppClassTeacherID,OwnerSessionID,ClassID,SectionID,ApiUserID,TeacherRole,IsActive FROM dbo.AppClassTeacher;
SET IDENTITY_INSERT dbo.AppClassTeacher_New OFF;

CREATE TABLE dbo.AppNotice_New(
 AppNoticeID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotice_V2 PRIMARY KEY,
 OwnerSessionID bigint NULL,NoticeType varchar(30) NOT NULL CONSTRAINT DF_AppNotice_Type_V2 DEFAULT 'general',TargetType varchar(20) NOT NULL CONSTRAINT DF_AppNotice_Target_V2 DEFAULT 'school',
 Title nvarchar(200) NOT NULL,Body nvarchar(max) NOT NULL,ClassID bigint NULL,SectionID bigint NULL,StudentID bigint NULL,PublishedAt datetime2 NOT NULL,
 PublishedBy nvarchar(150) NULL,CreatedByApiUserID varchar(64) NULL,AttachmentUrl nvarchar(1000) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppNotice_Active_V2 DEFAULT 1,CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppNotice_Created_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppNotice_Session_V2 FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppNotice_Class_V2 FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppNotice_Section_V2 FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID),
 CONSTRAINT FK_AppNotice_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID),
 CONSTRAINT CK_AppNotice_Type_V2 CHECK(NoticeType IN('general','academic','exam','event','holiday','fee','urgent')),
 CONSTRAINT CK_AppNotice_Target_V2 CHECK(TargetType IN('school','class','section','student')));
SET IDENTITY_INSERT dbo.AppNotice_New ON;
INSERT dbo.AppNotice_New(AppNoticeID,OwnerSessionID,NoticeType,TargetType,Title,Body,ClassID,SectionID,StudentID,PublishedAt,PublishedBy,CreatedByApiUserID,AttachmentUrl,IsActive,CreatedAt)
SELECT AppNoticeID,OwnerSessionID,NoticeType,TargetType,Title,Body,ClassID,SectionID,StudentID,PublishedAt,PublishedBy,CreatedByApiUserID,AttachmentUrl,IsActive,CreatedAt FROM dbo.AppNotice;
SET IDENTITY_INSERT dbo.AppNotice_New OFF;

CREATE TABLE dbo.AppNoticeRead_New(
 AppNoticeReadID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNoticeRead_V2 PRIMARY KEY,
 AppParentID int NOT NULL,AppNoticeID int NOT NULL,ReadAt datetime2 NOT NULL CONSTRAINT DF_AppNoticeRead_Date_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppNoticeRead_V2 UNIQUE(AppParentID,AppNoticeID),
 CONSTRAINT FK_AppNoticeRead_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppNoticeRead_Notice_V2 FOREIGN KEY(AppNoticeID) REFERENCES dbo.AppNotice_New(AppNoticeID));
INSERT dbo.AppNoticeRead_New(AppParentID,AppNoticeID,ReadAt)
SELECT m.NewID,o.AppNoticeID,o.ReadAt FROM dbo.AppNoticeRead o JOIN #ParentMap m ON m.OldID=o.AppParentID;

CREATE TABLE dbo.AppNotification_New(
 AppNotificationID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotification_V2 PRIMARY KEY,
 AppParentID int NULL,StudentID bigint NULL,NotificationType varchar(40) NOT NULL,Title nvarchar(200) NOT NULL,Body nvarchar(1000) NOT NULL,
 RelatedType varchar(40) NULL,RelatedID varchar(100) NULL,CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppNotification_Created_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppNotification_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppNotification_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));
SET IDENTITY_INSERT dbo.AppNotification_New ON;
INSERT dbo.AppNotification_New(AppNotificationID,AppParentID,StudentID,NotificationType,Title,Body,RelatedType,RelatedID,CreatedAt)
SELECT o.AppNotificationID,m.NewID,o.StudentID,o.NotificationType,o.Title,o.Body,o.RelatedType,o.RelatedID,o.CreatedAt FROM dbo.AppNotification o LEFT JOIN #ParentMap m ON m.OldID=o.AppParentID;
SET IDENTITY_INSERT dbo.AppNotification_New OFF;

CREATE TABLE dbo.AppNotificationRead_New(
 AppNotificationReadID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppNotificationRead_V2 PRIMARY KEY,
 AppParentID int NOT NULL,AppNotificationID int NOT NULL,ReadAt datetime2 NOT NULL CONSTRAINT DF_AppNotificationRead_Date_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppNotificationRead_V2 UNIQUE(AppParentID,AppNotificationID),
 CONSTRAINT FK_AppNotificationRead_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppNotificationRead_Notification_V2 FOREIGN KEY(AppNotificationID) REFERENCES dbo.AppNotification_New(AppNotificationID));
INSERT dbo.AppNotificationRead_New(AppParentID,AppNotificationID,ReadAt)
SELECT m.NewID,o.AppNotificationID,o.ReadAt FROM dbo.AppNotificationRead o JOIN #ParentMap m ON m.OldID=o.AppParentID;

CREATE TABLE dbo.AppParentDevice_New(
 AppParentDeviceID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentDevice_V2 PRIMARY KEY,
 AppParentID int NOT NULL,DeviceKey varchar(200) NOT NULL,FcmToken varchar(500) NOT NULL,Platform varchar(20) NOT NULL,AppVersion varchar(30) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppDevice_Active_V2 DEFAULT 1,UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppDevice_Updated_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppParentDevice_V2 UNIQUE(AppParentID,DeviceKey),CONSTRAINT FK_AppDevice_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID));
SET IDENTITY_INSERT dbo.AppParentDevice_New ON;
INSERT dbo.AppParentDevice_New(AppParentDeviceID,AppParentID,DeviceKey,FcmToken,Platform,AppVersion,IsActive,UpdatedAt)
SELECT o.AppParentDeviceID,m.NewID,o.DeviceKey,o.FcmToken,o.Platform,o.AppVersion,o.IsActive,o.UpdatedAt FROM dbo.AppParentDevice o JOIN #ParentMap m ON m.OldID=o.AppParentID;
SET IDENTITY_INSERT dbo.AppParentDevice_New OFF;

CREATE TABLE dbo.AppParentSetting_New(
 AppParentSettingID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppParentSetting_V2 PRIMARY KEY,
 AppParentID int NOT NULL CONSTRAINT UQ_AppParentSetting_Parent_V2 UNIQUE,
 AttendanceNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Attendance_V2 DEFAULT 1,FeeNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Fee_V2 DEFAULT 1,
 HomeworkNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Homework_V2 DEFAULT 1,NoticeNotifications bit NOT NULL CONSTRAINT DF_AppSetting_Notice_V2 DEFAULT 1,
 Language varchar(10) NOT NULL CONSTRAINT DF_AppSetting_Language_V2 DEFAULT 'en',UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppSetting_Updated_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT FK_AppSetting_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID));
INSERT dbo.AppParentSetting_New(AppParentID,AttendanceNotifications,FeeNotifications,HomeworkNotifications,NoticeNotifications,Language,UpdatedAt)
SELECT m.NewID,o.AttendanceNotifications,o.FeeNotifications,o.HomeworkNotifications,o.NoticeNotifications,o.Language,o.UpdatedAt FROM dbo.AppParentSetting o JOIN #ParentMap m ON m.OldID=o.AppParentID;

CREATE TABLE dbo.AppPayment_New(
 AppPaymentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppPayment_V2 PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,Amount numeric(18,2) NOT NULL,Currency char(3) NOT NULL CONSTRAINT DF_AppPayment_Currency_V2 DEFAULT 'INR',
 Provider varchar(30) NOT NULL,ProviderOrderID varchar(150) NULL,ProviderPaymentID varchar(150) NULL,Status varchar(30) NOT NULL CONSTRAINT DF_AppPayment_Status_V2 DEFAULT 'created',
 RequestReference varchar(200) NULL,CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppPayment_Created_V2 DEFAULT SYSUTCDATETIME(),UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppPayment_Updated_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT CK_AppPayment_Amount_V2 CHECK(Amount>0),CONSTRAINT FK_AppPayment_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppPayment_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));
MERGE dbo.AppPayment_New AS target USING(SELECT o.*,m.NewID NewParentID FROM dbo.AppPayment o JOIN #ParentMap m ON m.OldID=o.AppParentID) AS source ON 1=0
WHEN NOT MATCHED THEN INSERT(AppParentID,StudentID,Amount,Currency,Provider,ProviderOrderID,ProviderPaymentID,Status,RequestReference,CreatedAt,UpdatedAt)
VALUES(source.NewParentID,source.StudentID,source.Amount,source.Currency,source.Provider,source.ProviderOrderID,source.ProviderPaymentID,source.Status,source.RequestReference,source.CreatedAt,source.UpdatedAt)
OUTPUT source.AppPaymentID,inserted.AppPaymentID INTO #PaymentMap(OldID,NewID);

CREATE TABLE dbo.AppReceipt_New(
 AppReceiptID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppReceipt_V2 PRIMARY KEY,
 AppPaymentID int NULL,StudentID bigint NOT NULL,ReceiptNumber varchar(100) NOT NULL,Amount numeric(18,2) NOT NULL,ReceiptDate datetime2 NOT NULL,PaymentMode varchar(50) NULL,PdfPath nvarchar(1000) NULL,
 CONSTRAINT UQ_AppReceipt_Number_V2 UNIQUE(ReceiptNumber),CONSTRAINT FK_AppReceipt_Payment_V2 FOREIGN KEY(AppPaymentID) REFERENCES dbo.AppPayment_New(AppPaymentID),
 CONSTRAINT FK_AppReceipt_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));
SET IDENTITY_INSERT dbo.AppReceipt_New ON;
INSERT dbo.AppReceipt_New(AppReceiptID,AppPaymentID,StudentID,ReceiptNumber,Amount,ReceiptDate,PaymentMode,PdfPath)
SELECT o.AppReceiptID,m.NewID,o.StudentID,o.ReceiptNumber,o.Amount,o.ReceiptDate,o.PaymentMode,o.PdfPath FROM dbo.AppReceipt o LEFT JOIN #PaymentMap m ON m.OldID=o.AppPaymentID;
SET IDENTITY_INSERT dbo.AppReceipt_New OFF;

CREATE TABLE dbo.AppTeacherDevice_New(
 AppTeacherDeviceID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppTeacherDevice_V2 PRIMARY KEY,
 ApiUserID varchar(64) NOT NULL,DeviceKey varchar(200) NOT NULL,FcmToken varchar(500) NOT NULL,Platform varchar(20) NOT NULL,AppVersion varchar(30) NULL,
 IsActive bit NOT NULL CONSTRAINT DF_AppTeacherDevice_Active_V2 DEFAULT 1,UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppTeacherDevice_Updated_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppTeacherDevice_V2 UNIQUE(ApiUserID,DeviceKey));
SET IDENTITY_INSERT dbo.AppTeacherDevice_New ON;
INSERT dbo.AppTeacherDevice_New(AppTeacherDeviceID,ApiUserID,DeviceKey,FcmToken,Platform,AppVersion,IsActive,UpdatedAt)
SELECT AppTeacherDeviceID,ApiUserID,DeviceKey,FcmToken,Platform,AppVersion,IsActive,UpdatedAt FROM dbo.AppTeacherDevice;
SET IDENTITY_INSERT dbo.AppTeacherDevice_New OFF;

CREATE TABLE dbo.AppChatConversation_New(
 AppChatConversationID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatConversation_V2 PRIMARY KEY,
 AppParentID int NOT NULL,StudentID bigint NOT NULL,TeacherApiUserID varchar(64) NOT NULL,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_AppChatConversation_Created_V2 DEFAULT SYSUTCDATETIME(),UpdatedAt datetime2 NOT NULL CONSTRAINT DF_AppChatConversation_Updated_V2 DEFAULT SYSUTCDATETIME(),
 CONSTRAINT UQ_AppChatConversation_V2 UNIQUE(AppParentID,StudentID,TeacherApiUserID),
 CONSTRAINT FK_AppChatConversation_Parent_V2 FOREIGN KEY(AppParentID) REFERENCES dbo.AppParent_New(AppParentID),
 CONSTRAINT FK_AppChatConversation_Student_V2 FOREIGN KEY(StudentID) REFERENCES dbo.Student(StudentID));
MERGE dbo.AppChatConversation_New AS target USING(SELECT o.*,m.NewID NewParentID FROM dbo.AppChatConversation o JOIN #ParentMap m ON m.OldID=o.AppParentID) AS source ON 1=0
WHEN NOT MATCHED THEN INSERT(AppParentID,StudentID,TeacherApiUserID,CreatedAt,UpdatedAt)
VALUES(source.NewParentID,source.StudentID,source.TeacherApiUserID,source.CreatedAt,source.UpdatedAt)
OUTPUT source.AppChatConversationID,inserted.AppChatConversationID INTO #ConversationMap(OldID,NewID);

CREATE TABLE dbo.AppChatMessage_New(
 AppChatMessageID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatMessage_V2 PRIMARY KEY,
 AppChatConversationID int NOT NULL,SenderType varchar(10) NOT NULL,SenderID varchar(64) NOT NULL,Message nvarchar(max) NULL,
 SentAt datetime2 NOT NULL CONSTRAINT DF_AppChatMessage_Sent_V2 DEFAULT SYSUTCDATETIME(),ReadAt datetime2 NULL,
 CONSTRAINT CK_AppChatMessage_Sender_V2 CHECK(SenderType IN('parent','teacher')),
 CONSTRAINT FK_AppChatMessage_Conversation_V2 FOREIGN KEY(AppChatConversationID) REFERENCES dbo.AppChatConversation_New(AppChatConversationID));
SET IDENTITY_INSERT dbo.AppChatMessage_New ON;
INSERT dbo.AppChatMessage_New(AppChatMessageID,AppChatConversationID,SenderType,SenderID,Message,SentAt,ReadAt)
SELECT o.AppChatMessageID,m.NewID,o.SenderType,CASE WHEN o.SenderType='parent' AND pm.NewID IS NOT NULL THEN CONVERT(varchar(64),pm.NewID) ELSE o.SenderID END,o.Message,o.SentAt,o.ReadAt
FROM dbo.AppChatMessage o JOIN #ConversationMap m ON m.OldID=o.AppChatConversationID
LEFT JOIN #ParentMap pm ON CONVERT(varchar(36),pm.OldID)=o.SenderID;
SET IDENTITY_INSERT dbo.AppChatMessage_New OFF;

CREATE TABLE dbo.AppChatAttachment_New(
 AppChatAttachmentID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppChatAttachment_V2 PRIMARY KEY,
 AppChatMessageID int NOT NULL,FileName nvarchar(255) NOT NULL,MimeType varchar(150) NOT NULL,FileUrl nvarchar(1000) NOT NULL,FileSize bigint NULL,
 CONSTRAINT FK_AppChatAttachment_Message_V2 FOREIGN KEY(AppChatMessageID) REFERENCES dbo.AppChatMessage_New(AppChatMessageID));
SET IDENTITY_INSERT dbo.AppChatAttachment_New ON;
INSERT dbo.AppChatAttachment_New(AppChatAttachmentID,AppChatMessageID,FileName,MimeType,FileUrl,FileSize)
SELECT AppChatAttachmentID,AppChatMessageID,FileName,MimeType,FileUrl,FileSize FROM dbo.AppChatAttachment;
SET IDENTITY_INSERT dbo.AppChatAttachment_New OFF;

IF EXISTS(
 SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppParent)<>(SELECT COUNT_BIG(*) FROM dbo.AppParent_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppParentStudent)<>(SELECT COUNT_BIG(*) FROM dbo.AppParentStudent_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppParentAuthToken)<>(SELECT COUNT_BIG(*) FROM dbo.AppParentAuthToken_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppHomework)<>(SELECT COUNT_BIG(*) FROM dbo.AppHomework_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppClassTeacher)<>(SELECT COUNT_BIG(*) FROM dbo.AppClassTeacher_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppNotice)<>(SELECT COUNT_BIG(*) FROM dbo.AppNotice_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppNoticeRead)<>(SELECT COUNT_BIG(*) FROM dbo.AppNoticeRead_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppNotification)<>(SELECT COUNT_BIG(*) FROM dbo.AppNotification_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppNotificationRead)<>(SELECT COUNT_BIG(*) FROM dbo.AppNotificationRead_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppParentDevice)<>(SELECT COUNT_BIG(*) FROM dbo.AppParentDevice_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppParentSetting)<>(SELECT COUNT_BIG(*) FROM dbo.AppParentSetting_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppPayment)<>(SELECT COUNT_BIG(*) FROM dbo.AppPayment_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppReceipt)<>(SELECT COUNT_BIG(*) FROM dbo.AppReceipt_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppTeacherDevice)<>(SELECT COUNT_BIG(*) FROM dbo.AppTeacherDevice_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppChatConversation)<>(SELECT COUNT_BIG(*) FROM dbo.AppChatConversation_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppChatMessage)<>(SELECT COUNT_BIG(*) FROM dbo.AppChatMessage_New)
 UNION ALL SELECT 1 WHERE (SELECT COUNT_BIG(*) FROM dbo.AppChatAttachment)<>(SELECT COUNT_BIG(*) FROM dbo.AppChatAttachment_New)
)
BEGIN
    THROW 51000,'Row-count validation failed. Entire migration will be rolled back.',1;
END;

DROP TABLE dbo.AppChatAttachment;DROP TABLE dbo.AppChatMessage;DROP TABLE dbo.AppChatConversation;
DROP TABLE dbo.AppTeacherDevice;DROP TABLE dbo.AppReceipt;DROP TABLE dbo.AppPayment;DROP TABLE dbo.AppParentSetting;
DROP TABLE dbo.AppParentDevice;DROP TABLE dbo.AppNotificationRead;DROP TABLE dbo.AppNotification;
DROP TABLE dbo.AppNoticeRead;DROP TABLE dbo.AppNotice;DROP TABLE dbo.AppClassTeacher;DROP TABLE dbo.AppHomework;
DROP TABLE dbo.AppParentAuthToken;DROP TABLE dbo.AppParentStudent;DROP TABLE dbo.AppParent;

EXEC sys.sp_rename N'dbo.AppParent_New',N'AppParent';
EXEC sys.sp_rename N'dbo.AppParentStudent_New',N'AppParentStudent';
EXEC sys.sp_rename N'dbo.AppParentAuthToken_New',N'AppParentAuthToken';
EXEC sys.sp_rename N'dbo.AppHomework_New',N'AppHomework';
EXEC sys.sp_rename N'dbo.AppClassTeacher_New',N'AppClassTeacher';
EXEC sys.sp_rename N'dbo.AppNotice_New',N'AppNotice';
EXEC sys.sp_rename N'dbo.AppNoticeRead_New',N'AppNoticeRead';
EXEC sys.sp_rename N'dbo.AppNotification_New',N'AppNotification';
EXEC sys.sp_rename N'dbo.AppNotificationRead_New',N'AppNotificationRead';
EXEC sys.sp_rename N'dbo.AppParentDevice_New',N'AppParentDevice';
EXEC sys.sp_rename N'dbo.AppParentSetting_New',N'AppParentSetting';
EXEC sys.sp_rename N'dbo.AppPayment_New',N'AppPayment';
EXEC sys.sp_rename N'dbo.AppReceipt_New',N'AppReceipt';
EXEC sys.sp_rename N'dbo.AppTeacherDevice_New',N'AppTeacherDevice';
EXEC sys.sp_rename N'dbo.AppChatConversation_New',N'AppChatConversation';
EXEC sys.sp_rename N'dbo.AppChatMessage_New',N'AppChatMessage';
EXEC sys.sp_rename N'dbo.AppChatAttachment_New',N'AppChatAttachment';

COMMIT TRANSACTION;
PRINT 'Parent schema data migrated successfully to INT IDENTITY primary keys.';
GO
