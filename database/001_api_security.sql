-- Run this script while connected to the target application database.
-- Example: sqlcmd -d SchoolManagement -i 001_api_security.sql
IF OBJECT_ID('dbo.SchoolTeacher','U') IS NULL
CREATE TABLE dbo.SchoolTeacher(
 ApiUserID uniqueidentifier NOT NULL CONSTRAINT DF_ApiUser_ID DEFAULT NEWSEQUENTIALID() PRIMARY KEY,
 EmployeeID bigint NULL,SchoolBranchID bigint NOT NULL,DisplayName nvarchar(150) NOT NULL,Username varchar(100) NOT NULL,
 PasswordHash varchar(255) NOT NULL,Role varchar(30) NOT NULL CONSTRAINT CK_ApiUser_Role CHECK(Role IN('teacher','admin')),
 IsActive bit NOT NULL CONSTRAINT DF_ApiUser_Active DEFAULT 1,CreatedAt datetime2 NOT NULL CONSTRAINT DF_ApiUser_Created DEFAULT SYSUTCDATETIME(),
 PasswordChangedAt datetime2 NULL,LastLoginAt datetime2 NULL,CONSTRAINT UQ_ApiUser_Login UNIQUE(SchoolBranchID,Username),
 CONSTRAINT FK_ApiUser_Employee FOREIGN KEY(EmployeeID) REFERENCES dbo.Employee(EmployeeID),
 CONSTRAINT FK_ApiUser_School FOREIGN KEY(SchoolBranchID) REFERENCES dbo.SchoolBranch(SchoolBranchID));
GO
IF OBJECT_ID('dbo.ApiAuthToken','U') IS NULL
CREATE TABLE dbo.ApiAuthToken(
 ApiAuthTokenID int IDENTITY(1,1) PRIMARY KEY,ApiUserID uniqueidentifier NOT NULL,TokenHash char(64) NOT NULL UNIQUE,
 CreatedAt datetime2 NOT NULL CONSTRAINT DF_ApiToken_Created DEFAULT SYSUTCDATETIME(),ExpiresAt datetime2 NOT NULL,
 LastUsedAt datetime2 NULL,RevokedAt datetime2 NULL,IpAddress varchar(45) NULL,UserAgent nvarchar(500) NULL,
 CONSTRAINT FK_ApiToken_User FOREIGN KEY(ApiUserID) REFERENCES dbo.SchoolTeacher(ApiUserID));
GO
IF OBJECT_ID('dbo.ApiLoginAttempt','U') IS NULL
BEGIN
 CREATE TABLE dbo.ApiLoginAttempt(ApiLoginAttemptID int IDENTITY(1,1) PRIMARY KEY,AttemptKey char(64) NOT NULL,AttemptedAt datetime2 NOT NULL);
 CREATE INDEX IX_ApiLoginAttempt_KeyTime ON dbo.ApiLoginAttempt(AttemptKey,AttemptedAt);
END
GO
IF OBJECT_ID('dbo.ApiTeacherAssignment','U') IS NULL
CREATE TABLE dbo.ApiTeacherAssignment(
 ApiTeacherAssignmentID int IDENTITY(1,1) PRIMARY KEY,ApiUserID uniqueidentifier NOT NULL,OwnerSessionID bigint NOT NULL,
 ClassID bigint NOT NULL,SectionID bigint NOT NULL,SubjectID bigint NOT NULL,IsActive bit NOT NULL CONSTRAINT DF_ApiAssignment_Active DEFAULT 1,
 CONSTRAINT UQ_ApiAssignment UNIQUE(ApiUserID,OwnerSessionID,ClassID,SectionID,SubjectID),
 CONSTRAINT FK_ApiAssignment_User FOREIGN KEY(ApiUserID) REFERENCES dbo.SchoolTeacher(ApiUserID),
 CONSTRAINT FK_ApiAssignment_Session FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_ApiAssignment_Class FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_ApiAssignment_Section FOREIGN KEY(SectionID) REFERENCES dbo.SectionMaster(SectionMasterID),
 CONSTRAINT FK_ApiAssignment_Subject FOREIGN KEY(SubjectID) REFERENCES dbo.CBSEExamSubject(CBSEExamSubjectID));
GO

IF OBJECT_ID('dbo.AppSubjectMaxMark','U') IS NULL
CREATE TABLE dbo.AppSubjectMaxMark(
 AppSubjectMaxMarkID int IDENTITY(1,1) NOT NULL CONSTRAINT PK_AppSubjectMaxMark PRIMARY KEY,
 OwnerSessionID bigint NOT NULL,
 ClassID bigint NOT NULL,
 TermOptionID bigint NOT NULL,
 SubSubjectID bigint NOT NULL,
 MaxMarks numeric(10,2) NOT NULL,
 Serial int NULL,
 CONSTRAINT UQ_AppSubjectMaxMark UNIQUE(OwnerSessionID,ClassID,TermOptionID,SubSubjectID),
 CONSTRAINT CK_AppSubjectMaxMark_MaxMarks CHECK(MaxMarks>0 AND MaxMarks<=1000),
 CONSTRAINT FK_AppSubjectMaxMark_Session FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
 CONSTRAINT FK_AppSubjectMaxMark_Class FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
 CONSTRAINT FK_AppSubjectMaxMark_TermOption FOREIGN KEY(TermOptionID) REFERENCES dbo.CBSEExamTermOption(CBSEExamTermOptionID),
 CONSTRAINT FK_AppSubjectMaxMark_SubSubject FOREIGN KEY(SubSubjectID) REFERENCES dbo.CBSEExamSubSubject(CBSEExamSubSubjectID));
GO
