/* No schema change is required. The application accepts ApiUserID as UUID, int or bigint. */
SELECT TYPE_NAME(c.user_type_id) AS ApiUserIDType,c.max_length,c.is_nullable
FROM sys.columns c
WHERE c.object_id=OBJECT_ID(N'dbo.SchoolTeacher') AND c.name=N'ApiUserID';
GO
