/* Run in every tenant SchoolManagement database after 003 and 004. Read-only checks. */
SET NOCOUNT ON;

DECLARE @Required TABLE(TableName sysname,ColumnName sysname);
INSERT INTO @Required VALUES
('SchoolBranch','SchoolBranchID'),('SchoolTeacher','ApiUserID'),
('Student','StudentID'),('StudentSession','StudentID'),('StudentSession','OwnerSessionID'),
('StudentSession','ClassID'),('StudentSession','SectionID'),
('OwnerSession','OwnerSessionID'),('OwnerSession','StartDate'),('OwnerSession','EndDate'),
('AttItem','StudentID'),('AttItem','AttType'),
('StudentFees','StudentFeesID'),('StudentFees','StudentID'),
('StudentFeesItem','StudentFeesItemID'),('StudentFeesItem','StudentFeesID'),('StudentFeesItem','StudentID'),
('FeeReceipt','FeeReceiptID'),('FeeReceipt','StudentID'),
('SchoolTeacher','ApiUserID'),('SchoolTeacher','EmployeeID'),
('ApiTeacherAssignment','ApiUserID'),('AppSubjectMaxMark','AppSubjectMaxMarkID'),
('AppParent','AppParentID'),('AppClassTeacher','AppClassTeacherID');

SELECT r.TableName,r.ColumnName,
 CASE WHEN c.column_id IS NULL THEN 'MISSING' ELSE 'OK' END AS Status
FROM @Required r
LEFT JOIN sys.tables t ON t.name=r.TableName AND t.schema_id=SCHEMA_ID('dbo')
LEFT JOIN sys.columns c ON c.object_id=t.object_id AND c.name=r.ColumnName
ORDER BY CASE WHEN c.column_id IS NULL THEN 0 ELSE 1 END,r.TableName,r.ColumnName;

SELECT t.name AS TableName,c.column_id AS Ordinal,c.name AS ColumnName,ty.name AS DataType,c.max_length,c.precision,c.scale,c.is_nullable
FROM sys.tables t JOIN sys.columns c ON c.object_id=t.object_id JOIN sys.types ty ON ty.user_type_id=c.user_type_id
WHERE t.schema_id=SCHEMA_ID('dbo') AND t.name IN('StudentFees','StudentFeesItem','FeeReceipt','AttItem')
ORDER BY t.name,c.column_id;
