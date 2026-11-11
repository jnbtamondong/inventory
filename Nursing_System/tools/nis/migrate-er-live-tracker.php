<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../../includes/database-settings.php';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$settings=nursingDatabaseSettings('nursing_system');
try{
    $db=new mysqli($settings['host'],$settings['user'],$settings['password'],$settings['database'],$settings['port']);$db->set_charset('utf8mb4');
    foreach(['patients','patient_tracking','patient_movements','audit_logs'] as $table){$r=$db->query("SHOW TABLE STATUS WHERE Name='$table'")->fetch_assoc();if(!$r||$r['Engine']!=='InnoDB')throw new RuntimeException('Transactional clinical and audit tables are required.');}
    if(in_array('--rollback-empty',$argv,true)){
        $existing=[];
        foreach(['er_tracker_events','er_public_tracking','er_display_devices','er_display_settings'] as $table)if($db->query("SHOW TABLES LIKE '$table'")->num_rows)$existing[]=$table;
        // Block concurrent enrollment/device writes while verifying emptiness and dropping.
        if($existing)$db->query('LOCK TABLES '.implode(',',array_map(fn($table)=>"`$table` WRITE",$existing)));
        foreach(['er_public_tracking','er_tracker_events','er_display_devices'] as $table){if($db->query("SHOW TABLES LIKE '$table'")->num_rows&&(int)$db->query("SELECT COUNT(*) n FROM `$table`")->fetch_assoc()['n']){fwrite(STDERR,"Rollback refused: ER tracking/device history exists. Restore application code and retain the tables.\n");exit(1);}}
        foreach(['er_tracker_events','er_public_tracking','er_display_devices','er_display_settings'] as $table)$db->query("DROP TABLE IF EXISTS `$table`");
        $db->query('UNLOCK TABLES');
        echo "Unused ER display tables removed. Clinical records were not changed.\n";exit;
    }
    $db->multi_query(file_get_contents(__DIR__.'/../../database/migrations/20261026_er_live_tracker.up.sql'));
    do{if($r=$db->store_result())$r->free();}while($db->more_results()&&$db->next_result());
    require __DIR__.'/../../includes/er-live-tracker.php';if(!erTrackerReady($db))throw new RuntimeException('ER schema unavailable.');
    echo "ER live tracker schema installed. No existing patients were enrolled or changed.\n";
}catch(Throwable $e){fwrite(STDERR,'ER migration failed; code '.(int)$e->getCode().". Additive setup can be retried.\n");exit(1);}
