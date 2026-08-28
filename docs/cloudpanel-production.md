# CloudPanel production configuration

This project is deployed as a separate Generic PHP 8.2 site.

## Site

- Domain: `teacher-api.example.com` (replace with the live API domain)
- Root directory: `/public`
- PHP: 8.2 with `pdo_sqlsrv` and `sqlsrv`
- Nginx fallback: `try_files $uri $uri/ /index.php?$query_string;`
- Forward bearer auth: `fastcgi_param HTTP_AUTHORIZATION $http_authorization;`

## Database

- SQL Server endpoint: `127.0.0.1,1433`
- Database: `SchoolManagement`
- Use a dedicated `school_api` login with only `db_datareader` and `db_datawriter`.
- Run `database/001_api_security.sql` with `SchoolManagement` selected.

## Environment

Copy `.env.production.example` to `.env`, replace all example values, then restrict it to the site user with mode `600`. Never upload a local `.env` or use the `sa` login in the application.

## Upload

Upload only `.env.production.example`, `.gitignore`, `README.md`, `bin`, `database`, `docs`, `logs`, `postman`, `public`, and `src`. Do not upload `.env`, `.driver-temp`, `Windows_5.12.0RTW.zip`, or `.git`.
