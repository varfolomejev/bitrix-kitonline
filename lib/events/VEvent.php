<?php
namespace Varfolomejev\Sale\events;

IncludeModuleLangFile(__FILE__);

class VEvent {
	/**
	 * @throws \Bitrix\Main\LoaderException
	 */
	public static function registerKitOnlineModule()
	{
		\Bitrix\Main\Loader::includeModule('varfolomejev.kitonline');
	}

	/**
	 * @return \Bitrix\Main\EventResult
	 */
	public static function registerKitOnlineCashbox()
	{
		$data = array('\Varfolomejev\Sale\Kitonline' => '/' . VARFOLOMEJEV_KITONLINE_MODULE_RELATIVE_PATH . '/lib/Kitonline.php');
		$event = new \Bitrix\Main\EventResult(\Bitrix\Main\EventResult::SUCCESS, $data);
		return $event;
	}
}