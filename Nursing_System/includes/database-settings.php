<?php
declare(strict_types=1);
function nursingDatabaseSettings(string $logical): array
{
    if(!in_array($logical,['nursing_system','cssr_inventory'],true))throw new InvalidArgumentException('Unknown application database.');
    $path=__DIR__.'/../config/runtime-database.php';
    $saved=is_file($path)?require $path:[];
    $prefix=$logical==='cssr_inventory'?'CSSR_DB_':'NURSING_DB_';
    $values=array_replace(['host'=>'localhost','port'=>3306,'database'=>$logical,'user'=>'root','password'=>''],$saved[$logical]??[]);
    foreach(['host'=>'HOST','port'=>'PORT','database'=>'NAME','user'=>'USER','password'=>'PASSWORD'] as $key=>$suffix){
        $value=getenv($prefix.$suffix);if($value!==false)$values[$key]=$value;
    }
    $values['port']=(int)$values['port'];
    if($values['port']<1||$values['port']>65535)throw new RuntimeException('Invalid database port.');
    if(getenv('NURSING_APP_ENV')==='production'&&($values['user']==='root'||$values['password']===''))throw new RuntimeException('Production database credentials have not been configured.');
    return $values;
}
