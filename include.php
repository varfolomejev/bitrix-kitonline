<?php

IncludeModuleLangFile(__FILE__);

define('VARFOLOMEJEV_KITONLINE_MODULE_PATH', dirname(__FILE__));
define('VARFOLOMEJEV_KITONLINE_MODULE_RELATIVE_PATH', str_replace($_SERVER['DOCUMENT_ROOT'], '', VARFOLOMEJEV_KITONLINE_MODULE_PATH));

CModule::AddAutoloadClasses(
	'varfolomejev.kitonline',
	array(
		"VEvent" => "lib/events/VEvent.php",
		'\Varfolomejev\Sale\Kitonline' => '/lib/kitonline.php',
	)
);

//var_dump(
//	class_exists('\Varfolomejev\Sale\Kitonline')
//);exit;