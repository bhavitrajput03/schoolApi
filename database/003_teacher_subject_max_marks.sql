-- Run once against every tenant database, for example SchoolManagement.
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF OBJECT_ID(N'dbo.SchoolTeacher',N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.ApiUser',N'U') IS NOT NULL
    THROW 51000, 'Both ApiUser and SchoolTeacher exist; verify them before continuing.', 1;

IF OBJECT_ID(N'dbo.ApiUser',N'U') IS NOT NULL
   AND OBJECT_ID(N'dbo.SchoolTeacher',N'U') IS NULL
BEGIN
    EXEC sys.sp_rename N'dbo.ApiUser', N'SchoolTeacher';
END;

IF OBJECT_ID(N'dbo.SchoolTeacher',N'U') IS NULL
    THROW 51000, 'ApiUser or SchoolTeacher table was not found. The Teacher table is intentionally not renamed.', 1;

IF OBJECT_ID(N'dbo.AppSubjectMaxMark',N'U') IS NULL
BEGIN
    CREATE TABLE dbo.AppSubjectMaxMark(
        AppSubjectMaxMarkID int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_AppSubjectMaxMark PRIMARY KEY,
        OwnerSessionID bigint NOT NULL,
        ClassID bigint NOT NULL,
        TermOptionID bigint NOT NULL,
        SubSubjectID bigint NOT NULL,
        MaxMarks numeric(10,2) NOT NULL,
        Serial int NULL,
        CONSTRAINT UQ_AppSubjectMaxMark
            UNIQUE(OwnerSessionID,ClassID,TermOptionID,SubSubjectID),
        CONSTRAINT CK_AppSubjectMaxMark_MaxMarks
            CHECK(MaxMarks>0 AND MaxMarks<=1000),
        CONSTRAINT FK_AppSubjectMaxMark_Session
            FOREIGN KEY(OwnerSessionID) REFERENCES dbo.OwnerSession(OwnerSessionID),
        CONSTRAINT FK_AppSubjectMaxMark_Class
            FOREIGN KEY(ClassID) REFERENCES dbo.ClassMaster(ClassmasterID),
        CONSTRAINT FK_AppSubjectMaxMark_TermOption
            FOREIGN KEY(TermOptionID) REFERENCES dbo.CBSEExamTermOption(CBSEExamTermOptionID),
        CONSTRAINT FK_AppSubjectMaxMark_SubSubject
            FOREIGN KEY(SubSubjectID) REFERENCES dbo.CBSEExamSubSubject(CBSEExamSubSubjectID)
    );
END;

;WITH ExistingMaxMarks AS(
    SELECT marks.OwnerSessionID,
           roster.ClassID,
           marks.TermOptionID,
           marks.SubSubjectID,
           marks.MaxMarks,
           marks.Serial,
           ROW_NUMBER() OVER(
               PARTITION BY marks.OwnerSessionID,roster.ClassID,
                            marks.TermOptionID,marks.SubSubjectID
               ORDER BY marks.CBSEExamMarksEntryID DESC
           ) RowNumber
    FROM dbo.CBSEExamMarksEntry marks
    JOIN dbo.StudentSession roster
      ON roster.StudentID=marks.StudentID
     AND roster.OwnerSessionID=marks.OwnerSessionID
    WHERE marks.MaxMarks IS NOT NULL
      AND marks.MaxMarks>0
      AND marks.MaxMarks<=1000
      AND roster.IsLeave=0
), MaxMarkMapping AS(
    SELECT OwnerSessionID,ClassID,TermOptionID,SubSubjectID,MaxMarks,
           COALESCE(Serial,DENSE_RANK() OVER(
               PARTITION BY OwnerSessionID,ClassID,TermOptionID
               ORDER BY SubSubjectID
           )) Serial
    FROM ExistingMaxMarks
    WHERE RowNumber=1
)
MERGE dbo.AppSubjectMaxMark WITH (HOLDLOCK) AS target
USING MaxMarkMapping AS source
   ON target.OwnerSessionID=source.OwnerSessionID
  AND target.ClassID=source.ClassID
  AND target.TermOptionID=source.TermOptionID
  AND target.SubSubjectID=source.SubSubjectID
WHEN MATCHED THEN
    UPDATE SET target.MaxMarks=source.MaxMarks,
               target.Serial=source.Serial
WHEN NOT MATCHED THEN
    INSERT(OwnerSessionID,ClassID,TermOptionID,SubSubjectID,MaxMarks,Serial)
    VALUES(source.OwnerSessionID,source.ClassID,source.TermOptionID,
           source.SubSubjectID,source.MaxMarks,source.Serial);

COMMIT TRANSACTION;
GO

SELECT OBJECT_ID(N'dbo.SchoolTeacher',N'U') SchoolTeacherObjectID,
       OBJECT_ID(N'dbo.AppSubjectMaxMark',N'U') AppSubjectMaxMarkObjectID;
GO
