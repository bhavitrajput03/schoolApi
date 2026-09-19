<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

use App\Core\Database;

if(PHP_SAPI!=='cli'||$argc!==2){
    fwrite(STDERR,"Usage: php bin/rebuild-empty-parent-schema.php SCHOOL_CODE\n");
    exit(1);
}

$school=strtoupper(trim((string)$argv[1]));
$files=[
    dirname(__DIR__).'/database/007_rebuild_empty_parent_schema_as_int.sql',
    dirname(__DIR__).'/database/004_parent_app.sql',
];

try{
    Database::useSchoolCode($school);
    $db=Database::connection();
    foreach($files as $file){
        $sql=file_get_contents($file);
        if($sql===false)throw new RuntimeException('Could not read '.basename($file));
        foreach(preg_split('/^\s*GO\s*$/mi',$sql)?:[] as $batch){
            $batch=trim($batch);
            if($batch!=='')$db->exec($batch);
        }
    }
    echo "Empty parent schema rebuilt for {$school} with INT IDENTITY primary keys.\n";
}catch(Throwable $error){
    fwrite(STDERR,"Parent schema rebuild failed: {$error->getMessage()}\n");
    exit(1);
}
