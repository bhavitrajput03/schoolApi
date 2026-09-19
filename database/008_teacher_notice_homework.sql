-- Adds teacher-created notice/homework metadata without deleting existing data.
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.AppNotice',N'U') IS NULL OR OBJECT_ID(N'dbo.AppHomework',N'U') IS NULL
BEGIN
    THROW 51000,'Run database/004_parent_app.sql first; AppNotice or AppHomework is missing.',1;
END;

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

IF NOT EXISTS(SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.AppNotice') AND name=N'IX_AppNotice_ParentFeed')
    CREATE INDEX IX_AppNotice_ParentFeed ON dbo.AppNotice(OwnerSessionID,ClassID,SectionID,StudentID,IsActive,PublishedAt DESC);
IF NOT EXISTS(SELECT 1 FROM sys.indexes WHERE object_id=OBJECT_ID(N'dbo.AppHomework') AND name=N'IX_AppHomework_ParentFeed')
    CREATE INDEX IX_AppHomework_ParentFeed ON dbo.AppHomework(OwnerSessionID,ClassID,SectionID,SubjectID,IsActive,HomeworkDate DESC);

COMMIT TRANSACTION;
GO
