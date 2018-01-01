<?php
IncludeModuleLangFile(__FILE__);

class VEvent {
	public static function registerKitOnlineModule()
	{
		\Bitrix\Main\Loader::includeModule('varfolomejev.kitonline');
	}

	public static function registerKitOnlineCashbox()
	{
		$data = array('\Varfolomejev\Sale\Kitonline' => VARFOLOMEJEV_KITONLINE_MODULE_RELATIVE_PATH . '/lib/kitonline.php');
		$event = new Bitrix\Main\EventResult(Bitrix\Main\EventResult::SUCCESS, $data);
		return $event;
	}

}