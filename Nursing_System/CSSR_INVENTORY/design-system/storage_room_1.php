<?php
require_once __DIR__.'/../includes/design-system.php';require_once __DIR__.'/../includes/storage-room-data.php';require_once __DIR__.'/../includes/storage-room-view.php';
require_once __DIR__.'/preview-navigation.php';require_once __DIR__.'/storage-room-samples.php';
cssrRenderStorageRoom(cssrStorageRoomData(cssrStorageRoomSamples(),$_GET),['title'=>'Storage Room 1 preview','home'=>'dashboard.php','active'=>'storage','navigation'=>cssrPreviewNavigation(),
    'searchAction'=>'storage_room_1.php','searchPlaceholder'=>'Search stored supplies…','initials'=>'SA','user'=>'Sample Admin','role'=>'Super Admin · Preview',
    'settings'=>'/Nursing_System/pages/account_settings.php','environment'=>'Storage Room 1 design preview','notificationText'=>'Sample stock only. No hospital records are shown.',
],['preview'=>true,'manage'=>true]);
