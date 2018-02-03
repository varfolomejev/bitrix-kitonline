<?php

namespace Varfolomejev\Sale;

use Bitrix\Main;
use Bitrix\Main\Localization;
use Bitrix\Sale\Cashbox\Cashbox;
use Bitrix\Sale\Cashbox\Check;
use Bitrix\Sale\Cashbox\IPrintImmediately;
use Bitrix\Sale\Cashbox\SellCheck;
use Bitrix\Sale\Cashbox\SellReturnCashCheck;
use Bitrix\Sale\Cashbox\SellReturnCheck;
use Bitrix\Sale\Result;
use Bitrix\Sale\Cashbox\Errors\Warning;
use Bitrix\Sale\Cashbox\Errors\Error;

Localization\Loc::loadMessages(__FILE__);

/**
 * Class CashboxAtolFarm
 * @package Bitrix\Sale\Cashbox
 */
class Kitonline extends Cashbox implements IPrintImmediately
{
	const SEND_CHECK_URL = 'https://api.kit-invest.ru/WebService.svc/SendCheck';
	const SEND_CHECK_STATE_URL = 'https://api.kit-invest.ru/WebService.svc/StateCheck';

	/**
	 * @return string
	 */
	public static function getName()
	{
		return Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_CASHBOX_NAME');
	}

	/**
	 * @param Check $check
	 * @return array
	 */
	public function buildCheckQuery(Check $check)
	{
		$kitOnlineService = new KitOnlineService();
		return $kitOnlineService->prepareCheckRequest($this, $check);
	}

	/**
	 * @param array $data
	 *
	 * @return array|\Exception
	 */
	public static function extractCheckData(array $data)
	{
		$result = array();
		if (!$data['EXTERNAL_UUID'])
			return $result;

		$selfModel = self::create(array_merge(array('SETTINGS' => $data['SETTINGS']), array(
			'HANDLER' => '\Varfolomejev\Sale\Kitonline'
		)));

		$kitOnlineService = new KitOnlineService();
		$checkQuery = $kitOnlineService->prepareCheckStateRequest(array('kit_online_check_queue_id' => $data['EXTERNAL_UUID']), $selfModel);
		$apiResult = $selfModel->send(self::SEND_CHECK_STATE_URL, $checkQuery);
		if ($apiResult instanceof \Exception || !$apiResult->isSuccess()){
			$result['ERROR'] = array(
				'CODE' => 500,
				'MESSAGE' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_API_SERVER_ERROR'),
				'TYPE' => Warning::TYPE
			);
			return $result;
		}
		$response = $apiResult->getData();

		if($response['CheckState']['ResultCode'] != 0 || $response['CheckState']['State'] != 1000){
			$result['ERROR'] = array(
				'CODE' => $response['CheckState']['ResultCode'] ? $response['CheckState']['ResultCode'] : $response['CheckState']['State'],
				'MESSAGE' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_API_SERVER_CHE�K_STATE_ERROR'),
				'TYPE' => Error::TYPE
			);
			return $result;
		}

		$result['ID'] = $data['ID'];
		$result['CHECK_TYPE'] = $data['TYPE'];
		$result['LINK_PARAMS'] = array(); //in the future we will have some link, maybe)
		return $result;
	}

	/**
	 * @param $id
	 * @return array
	 */
	public function buildZReportQuery($id)
	{
		return array();
	}

	/**
	 * @param array $data
	 * @return array
	 */
	protected static function extractZReportData(array $data)
	{
		return array();
	}

	/**
	 * @return array
	 */
	public function getCheckTypeMap()
	{
		return array(
			SellCheck::getType() => 1,
			SellReturnCashCheck::getType() => 2,
			SellReturnCheck::getType() => 2
		);
	}

	/**
	 * @param Check $check
	 * @return Result
	 */
	public function printImmediately(Check $check)
	{
		$printResult = new Result();
		$checkQuery = static::buildCheckQuery($check);
		$result = $this->send(self::SEND_CHECK_URL, $checkQuery);
		if ($result instanceof \Exception || !$result->isSuccess()){
			return $result;
		}
		$response = $result->getData();
//		file_put_contents(VARFOLOMEJEV_KITONLINE_MODULE_PATH . '/test.json', json_encode($response));
		if(isset($response['CheckQueueId'])){
			$printResult->setData(array('UUID' => $response['CheckQueueId']));
		} else {
			$printResult->addError(new Main\Error($response['ResultCode'] . ': ' . $response['ErrorMessage']));
		}

		return $printResult;
	}

	/**
	 * @param $url
	 * @param array $data
	 * @return Result
	 *
	 * @throws Main\ArgumentException
	 */
	private function send($url, array $data)
	{
		$result = new Result();
		$http = new Main\Web\HttpClient(array('disableSslVerification' => true));
		$response = $http->post($url, $this->encode($data));
		try {
			$response = $this->decode($response);
		} catch(Main\ArgumentException $exception){
			/**
			 * this exception will only if we are blocked on API side
			 */
			throw $exception;
		}

		if($response['ResultCode']){
			$result->addError(new Main\Error($response['ErrorMessage'], $response['ResultCode']));
		} else {
			foreach ($response as $key => $value){
				$result->addData([$key => $value]);
			}
		}
		return $result;
	}

	/**
	 * @param int $modelId
	 * @return array
	 */
	public static function getSettings($modelId = 0)
	{
		$settings = array(
			'AUTH' => array(
				'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_SETTINGS_AUTH'),
				'ITEMS' => array(
					'LOGIN' => array(
						'TYPE' => 'STRING',
						'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_LOGIN')
					),
					'PASSWORD' => array(
						'TYPE' => 'STRING',
						'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_PASSWORD')
					),
					'COMPANY_ID' => array(
						'TYPE' => 'STRING',
						'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_COMPANY_ID')
					),
					'TAX_SYSTEM_TYPE' => array(
						'TYPE' => 'ENUM',
						'OPTIONS' => array(
							1 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_1'),
							2 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_2'),
							4 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_4'),
							8 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_8'),
							16 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_16'),
							32 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE_32'),
						),
						'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_SYSTEM_TYPE')
					),
					'TAX' => array(
						'TYPE' => 'ENUM',
						'OPTIONS' => array(
							1 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_1'),
							2 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_2'),
							3 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_3'),
							4 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_4'),
							5 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_5'),
							6 => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX_6'),
						),
						'LABEL' => Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_TAX')
					),
				)
			),
		);
		return $settings;
	}

	/**
	 * @param $data
	 * @return Result
	 */
	public static function validateFields($data)
	{
		$result = new Result();

		if (empty($data['NUMBER_KKM']))
		{
			$result->addError(new Main\Error(Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_NUMBER_KKM_ERROR')));
		}

		if (empty($data['SETTINGS']['AUTH']['LOGIN']))
		{
			$result->addError(new Main\Error(Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_LOGIN_ERROR')));
		}

		if (empty($data['SETTINGS']['AUTH']['PASSWORD']))
		{
			$result->addError(new Main\Error(Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_PASSWORD_ERROR')));
		}

		if (empty($data['SETTINGS']['AUTH']['COMPANY_ID']))
		{
			$result->addError(new Main\Error(Localization\Loc::getMessage('VARFOLOMEJEV_KITONLINE_COMPANY_ID_ERROR')));
		}

		return $result;
	}

	/**
	 * @param array $data
	 * @return mixed
	 */
	private function encode(array $data)
	{
		return Main\Web\Json::encode($data);
	}

	/**
	 * @param string $data
	 * @return mixed
	 */
	private function decode($data)
	{
		return Main\Web\Json::decode($data);
	}
}
