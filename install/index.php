<?php
/*
global $MESS;
$strPath2Lang = str_replace("\\", "/", __FILE__);
$strPath2Lang = substr($strPath2Lang, 0, strlen($strPath2Lang)-strlen("/install/index.php"));
include(GetLangFileName($strPath2Lang."/lang/", "/install/index.php"));
*/
Class varfolomejev_kitonline extends CModule
{
	public $MODULE_ID = "varfolomejev.kitonline";
	public $MODULE_VERSION;
	public $MODULE_VERSION_DATE;
	public $MODULE_NAME;
	public $MODULE_DESCRIPTION;
	public $MODULE_CSS;

	public function varfolomejev_kitonline()
	{
		$arModuleVersion = array();
		$path = str_replace("\\", "/", __FILE__);
		$path = substr($path, 0, strlen($path) - strlen("/index.php"));
		include($path."/version.php");
		if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion))
		{
			$this->MODULE_VERSION = $arModuleVersion["VERSION"];
			$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		}

		$this->PARTNER_NAME = "Varfolomejev Igor";
		$this->PARTNER_URI = "http://www.varfolomejev.com/";
		$this->MODULE_NAME = GetMessage("VARFOLOMEJEV_KITONLINE_MODULE_NAME");
		$this->MODULE_DESCRIPTION = GetMessage("VARFOLOMEJEV_KITONLINE_MODULE_DESCRIPTION");
	}

	function InstallDB($install_wizard = true)
	{
		global $DB, $DBType, $APPLICATION;
		RegisterModule($this->MODULE_ID);
		CAgent::AddAgent(
			'\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();',
			$this->MODULE_ID,
			"Y",
			30,
			"",
			"Y",
			"",
			30);
		return true;
	}

	function UnInstallDB($arParams = Array())
	{
		global $DB, $DBType, $APPLICATION;
		UnRegisterModule($this->MODULE_ID);
		CAgent::RemoveAgent(
			'\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();',
			$this->MODULE_ID
		);
		return true;
	}

	function InstallEvents()
	{
		RegisterModuleDependences("main", "OnBeforeProlog", "varfolomejev.kitonline", '\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();', 'registerKitOnlineModule');
		RegisterModuleDependences("sale", "OnGetCustomCashboxHandlers", "varfolomejev.kitonline", '\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();', 'registerKitOnlineCashbox');
		return true;
	}

	function UnInstallEvents()
	{
		UnRegisterModuleDependences("main", "OnBeforeProlog", "varfolomejev.kitonline", '\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();', 'registerKitOnlineModule');
		UnRegisterModuleDependences("sale", "OnGetCustomCashboxHandlers", "varfolomejev.kitonline", '\Varfolomejev\Sale\events\VEvent::checkCashBoxCheck();', 'registerKitOnlineCashbox');
		return true;
	}

	function InstallFiles()
	{
		return true;
	}

	function InstallPublic()
	{
	}

	function UnInstallFiles()
	{
		return true;
	}

	function DoInstall()
	{
		global $APPLICATION, $step;

		$this->InstallFiles();
		$this->InstallDB(false);
		$this->InstallEvents();
		$this->InstallPublic();

		$APPLICATION->IncludeAdminFile(GetMessage("VARFOLOMEJEV_KITONLINE_INSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/varfolomejev.kitonline/install/step.php");
		return true;
	}

	function DoUninstall()
	{
		global $APPLICATION, $step;

		$this->UnInstallDB();
		$this->UnInstallFiles();
		$this->UnInstallEvents();
		$APPLICATION->IncludeAdminFile(GetMessage("VARFOLOMEJEV_KITONLINE_UNINSTALL_TITLE"), $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/varfolomejev.kitonline/install/unstep.php");
		return true;
	}

}