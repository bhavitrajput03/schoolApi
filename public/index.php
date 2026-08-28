<?php
declare(strict_types=1);
use App\Core\App;
use App\Core\Response;
require dirname(__DIR__) . '/src/bootstrap.php';
try { App::run(); }
catch (Throwable $e) { error_log($e->__toString()); Response::error('Internal server error.', 500, 'INTERNAL_ERROR'); }

