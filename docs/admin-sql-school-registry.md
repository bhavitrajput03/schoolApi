# Central SQL Server school registry

The API no longer uses MySQL for tenant routing. The central SQL Server database is `admineyetab` and contains one routing table:

```text
dbo.SchoolConnection: ID + SchoolCode + ConnectionString
```

The `.env` file contains `ADMIN_DB_CONNECTION_STRING`, which is the bootstrap connection to `admineyetab`. The connection string returned by `dbo.SchoolConnection` must include `Server`, `Database`, `User Id`, `Password`, `Encrypt`, and `TrustServerCertificate`.

Example local-to-live tunnel row:

```text
Server=127.0.0.1,14330;Database=SchoolManagement;User Id=school_api;Password=SECRET;Encrypt=false;TrustServerCertificate=true
```

Example VPS-local row:

```text
Server=127.0.0.1,1433;Database=SchoolManagement;User Id=school_api;Password=SECRET;Encrypt=true;TrustServerCertificate=true
```

Because this table contains full credentials, grant its read/write permissions only to the dedicated admin-registry API login.
