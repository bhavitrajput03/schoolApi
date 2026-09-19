-- Converts an EMPTY parent-app schema to INT IDENTITY primary keys.
-- The script deliberately stops if any app table contains data.
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF EXISTS(
    SELECT 1
    FROM sys.tables t
    CROSS APPLY (SELECT SUM(p.rows) AS TotalRows FROM sys.partitions p WHERE p.object_id=t.object_id AND p.index_id IN(0,1)) r
    WHERE t.name IN(
        'AppParent','AppParentStudent','AppParentAuthToken','AppHomework','AppClassTeacher',
        'AppNotice','AppNoticeRead','AppNotification','AppNotificationRead','AppParentDevice',
        'AppParentSetting','AppPayment','AppReceipt','AppTeacherDevice','AppChatConversation',
        'AppChatMessage','AppChatAttachment'
    ) AND ISNULL(r.TotalRows,0)>0
)
BEGIN
    THROW 51000,'Parent app tables contain data. Migration stopped without deleting anything.',1;
END;

DROP TABLE IF EXISTS dbo.AppChatAttachment;
DROP TABLE IF EXISTS dbo.AppChatMessage;
DROP TABLE IF EXISTS dbo.AppChatConversation;
DROP TABLE IF EXISTS dbo.AppTeacherDevice;
DROP TABLE IF EXISTS dbo.AppReceipt;
DROP TABLE IF EXISTS dbo.AppPayment;
DROP TABLE IF EXISTS dbo.AppParentSetting;
DROP TABLE IF EXISTS dbo.AppParentDevice;
DROP TABLE IF EXISTS dbo.AppNotificationRead;
DROP TABLE IF EXISTS dbo.AppNotification;
DROP TABLE IF EXISTS dbo.AppNoticeRead;
DROP TABLE IF EXISTS dbo.AppNotice;
DROP TABLE IF EXISTS dbo.AppClassTeacher;
DROP TABLE IF EXISTS dbo.AppHomework;
DROP TABLE IF EXISTS dbo.AppParentAuthToken;
DROP TABLE IF EXISTS dbo.AppParentStudent;
DROP TABLE IF EXISTS dbo.AppParent;

COMMIT TRANSACTION;
GO
