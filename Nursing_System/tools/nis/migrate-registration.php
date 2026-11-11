<?php
// Explicit CLI-only additive migration; no request-time schema changes.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../includes/database-settings.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$settings=nursingDatabaseSettings('nursing_system');
try{
 $db=new mysqli($settings['host'],$settings['user'],$settings['password'],$settings['database'],$settings['port']);$db->set_charset('utf8mb4');
 foreach(['patients'] as $table){$r=$db->query("SHOW TABLE STATUS WHERE Name='$table'")->fetch_assoc();if(!$r||$r['Engine']!=='InnoDB')throw new RuntimeException('Transactional patient table is required.');}
 if(in_array('--rollback-empty',$argv,true)){
  $lock=$db->query("SELECT GET_LOCK(CONCAT(DATABASE(),':registration'),10) acquired")->fetch_assoc();if((int)$lock['acquired']!==1)throw new RuntimeException('Registration is busy.');
  $tables=['nis_registration_requests','nis_registration_audit','nis_registrations','nis_patient_identities'];
  foreach($tables as $table){$exists=$db->query("SHOW TABLES LIKE '$table'")->num_rows;if($exists&&(int)$db->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n']>0){fwrite(STDERR,"Rollback refused: registration data exists. Preserve the tables and restore application code only.\n");exit(1);}}
  foreach($tables as $table)$db->query("DROP TABLE IF EXISTS `$table`");
  $db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':registration'))");echo "Empty registration schema removed. Existing patients were not changed.\n";exit;
 }
 $sql=file_get_contents(__DIR__.'/../../database/migrations/20261026_patient_registration.up.sql');
 if(!$db->multi_query($sql))throw new RuntimeException('Migration failed.');
 do{if($r=$db->store_result())$r->free();}while($db->more_results()&&$db->next_result());
 require_once __DIR__.'/../../includes/patient-registration.php';
 if(!registrationReady($db))throw new RuntimeException('Registration schema or transactional audit table is unavailable.');
 echo "Patient registration schema is installed. Existing patient records were not modified.\n";
}catch(Throwable $e){fwrite(STDERR,'Registration migration failed; database code '.(int)$e->getCode().". No existing patient records were changed.\n");exit(1);}
