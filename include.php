<?php
IncludeModuleLangFile(__FILE__);

define('VARFOLOMEJEV_KITONLINE_MODULE_PATH', dirname(__FILE__));
define('VARFOLOMEJEV_KITONLINE_MODULE_RELATIVE_PATH', str_replace($_SERVER['DOCUMENT_ROOT'], '', VARFOLOMEJEV_KITONLINE_MODULE_PATH));

CModule::AddAutoloadClasses(
	'kitinvest.kitonline',
	array(
		'\Varfolomejev\Sale\events\VEvent' => "lib/events/VEvent.php",
		'\Varfolomejev\Sale\Kitonline' => '/lib/Kitonline.php',
		'\Varfolomejev\Sale\KitOnlineService' => '/lib/KitOnlineService.php',
        'KitOnlineCron' => '/lib/KitOnlineCron.php',
	)
);
