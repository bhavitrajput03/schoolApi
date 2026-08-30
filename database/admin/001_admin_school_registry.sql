IF DB_ID(N'admineyetab') IS NULL
BEGIN
    CREATE DATABASE admineyetab;
END
GO

USE admineyetab;
GO

IF OBJECT_ID(N'dbo.SchoolConnection', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.SchoolConnection(
        ID bigint IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_SchoolConnection PRIMARY KEY,
        SchoolCode varchar(50) NOT NULL,
        ConnectionString nvarchar(2000) NOT NULL,
        CONSTRAINT UQ_SchoolConnection_SchoolCode UNIQUE(SchoolCode)
    );
END
GO

-- Reuse the existing least-privilege API SQL login when it exists.
-- The login password is not created or changed by this script.
IF SUSER_ID(N'school_api') IS NOT NULL
BEGIN
    IF USER_ID(N'school_api') IS NULL
        CREATE USER [school_api] FOR LOGIN [school_api];

    GRANT SELECT, INSERT, UPDATE ON dbo.SchoolConnection TO [school_api];
END
GO
