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
use Bitrix\Catalog;
use function print_r;

Localization\Loc::loadMessages(__FILE__);

/**
 * Class CashboxAtolFarm
 * @package Bitrix\Sale\Cashbox
 */
class Kitonline extends Cashbox implements IPrintImmediately
{
	const SERVICE_URL = 'https://api.kit-invest.ru/WebService.svc';

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
		$data = $check->getDataForCheck();

		echo '<pre>';print_r($data);exit;

		/** @var Main\Type\DateTime $dateTime */
		$dateTime = $data['date_create'];

		$phone = \NormalizePhone($data['client_phone']);
		if (is_string($phone))
		{
			if ($phone[0] === '7')
				$phone = substr($phone, 1);
		}
		else
		{
			$phone = '';
		}

		$result = array(
			'timestamp' => $dateTime->format('d.m.Y H:i:s'),
			'external_id' => static::buildUuid(static::UUID_TYPE_CHECK, $data['unique_id']),
			'service' => array(
				'inn' => $this->getValueFromSettings('SERVICE', 'INN'),
				'callback_url' => $this->getCallbackUrl(),
				'payment_address' => $this->getValueFromSettings('SERVICE', 'P_ADDRESS'),
			),
			'receipt' => array(
				'attributes' => array(
					'email' => $data['client_email'] ?: '',
					'phone' => $phone,
					'sno' => $this->getValueFromSettings('TAX', 'SNO'),
				),
				'payments' => array(),
				'items' => array(),
				'total' => (float)$data['total_sum']
			)
		);

		foreach ($data['payments'] as $payment)
		{
			$result['receipt']['payments'][] = array(
				'type' => (int)$this->getValueFromSettings('PAYMENT_TYPE', $payment['is_cash']),
				'sum' => (float)$payment['sum']
			);
		}

		foreach ($data['items'] as $i => $item)
		{
			$vat = $this->getValueFromSettings('VAT', $item['vat']);

			$result['receipt']['items'][] = array(
				'name' => $item['name'],
				'price' => (float)$item['price'],
				'sum' => (float)$item['sum'],
				'quantity' => $item['quantity'],
				'tax' => ($vat !== null) ? $vat : 'none'
			);
		}

		return $result;
	}

	/**
	 * @return string
	 */
	private function getCallbackUrl()
	{
		$context = Main\Application::getInstance()->getContext();
		$scheme = $context->getRequest()->isHttps() ? 'https' : 'http';
		$server = $context->getServer();
		$domain = $server->getServerName();

		if (preg_match('/^(?<domain>.+):(?<port>\d+)$/', $domain, $matches))
		{
			$domain = $matches['domain'];
			$port   = $matches['port'];
		}
		else
		{
			$port = $server->getServerPort();
		}
		$port = in_array($port, array(80, 443)) ? '' : ':'.$port;

		return sprintf('%s://%s%s/bitrix/tools/sale_farm_check_print.php', $scheme, $domain, $port);
	}



	/**
	 * @param array $data
	 * @return array
	 */
	protected static function extractCheckData(array $data)
	{
		$result = array();

		if (!$data['uuid'])
			return $result;

		$checkInfo = CheckManager::getCheckInfoByExternalUuid($data['uuid']);

		if ($data['error'])
		{
			$errorType = static::getErrorType($data['error']['code']);

			$result['ERROR'] = array(
				'CODE' => $data['error']['code'],
				'MESSAGE' => $data['error']['text'],
				'TYPE' => ($errorType === Errors\Error::TYPE) ? Errors\Error::TYPE : Errors\Warning::TYPE
			);
		}

		$result['ID'] = $checkInfo['ID'];
		$result['CHECK_TYPE'] = $checkInfo['TYPE'];

		$check = CheckManager::getObjectById($checkInfo['ID']);
		$dateTime = new Main\Type\DateTime($data['payload']['receipt_datetime']);
		$result['LINK_PARAMS'] = array(
			Check::PARAM_REG_NUMBER_KKT => $data['payload']['ecr_registration_number'],
			Check::PARAM_FISCAL_DOC_ATTR => $data['payload']['fiscal_document_attribute'],
			Check::PARAM_FISCAL_DOC_NUMBER => $data['payload']['fiscal_document_number'],
			Check::PARAM_FISCAL_RECEIPT_NUMBER => $data['payload']['fiscal_receipt_number'],
			Check::PARAM_FN_NUMBER => $data['payload']['fn_number'],
			Check::PARAM_SHIFT_NUMBER => $data['payload']['shift_number'],
			Check::PARAM_DOC_SUM => $data['payload']['total'],
			Check::PARAM_DOC_TIME => $dateTime->getTimestamp(),
			Check::PARAM_CALCULATION_ATTR => $check::getCalculatedSign()
		);

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
	private function getCheckTypeMap()
	{
		return array(
			SellCheck::getType() => 1,
			SellReturnCashCheck::getType() => 2,
			SellReturnCheck::getType() => 2
		);
	}

	/**
	 * @param $checkType
	 * @param $token
	 * @return string
	 */
	private function createUrlOperation($checkType, $token)
	{
		$groupCode = $this->getField('NUMBER_KKM');

		return static::SERVICE_URL.'/'.$groupCode.'/'.$checkType.'?tokenid='.$token;
	}

	/**
	 * @param Check $check
	 * @return Result
	 */
	public function printImmediately(Check $check)
	{
		$printResult = new Result();
		$checkQuery = static::buildCheckQuery($check);
		$validateResult = $this->validate($checkQuery);
		if (!$validateResult->isSuccess())
		{
			return $validateResult;
		}

		$checkTypeMap = $this->getCheckTypeMap();
		$checkType = $checkTypeMap[$check::getType()];

		$url = $this->createUrlOperation($checkType, $token);

		$result = $this->send($url, $checkQuery);
		if (!$result->isSuccess())
			return $result;

		$response = $result->getData();
		if ($response['http_code'] === static::RESPONSE_HTTP_CODE_401)
		{
			$token = $this->requestAccessToken();
			if ($token === '')
			{
				$printResult->addError(new Main\Error(Localization\Loc::getMessage('SALE_CASHBOX_ATOL_REQUEST_TOKEN_ERROR')));
				return $printResult;
			}

			$url = $this->createUrlOperation($checkType, $token);
			$result = $this->send($url, $checkQuery);
			if (!$result->isSuccess())
				return $result;

			$response = $result->getData();
		}

		if ($response['http_code'] === static::RESPONSE_HTTP_CODE_200)
		{
			if ($response['uuid'])
			{
				$printResult->setData(array('UUID' => $response['uuid']));
			}
			else
			{
				$printResult->addError(new Main\Error(Localization\Loc::getMessage('SALE_CASHBOX_ATOL_CHECK_REG_ERROR')));
			}
		}
		else
		{
			if (isset($response['error']['text']))
			{
				$printResult->addError(new Main\Error($response['error']['text']));
			}
			else
			{
				$printResult->addError(new Main\Error(Localization\Loc::getMessage('SALE_CASHBOX_ATOL_CHECK_REG_ERROR')));
			}
		}

		return $printResult;
	}





	/**
	 * @param array $checkData
	 * @return Result
	 */
	private function validate(array $checkData)
	{
		$result = new Result();

		if ($checkData['receipt']['attributes']['email'] === '' && $checkData['receipt']['attributes']['phone'] === '')
		{
			$result->addError(new Main\Error(Localization\Loc::getMessage('SALE_CASHBOX_ATOL_ERR_EMPTY_PHONE_EMAIL')));
		}

		return $result;
	}

	/**
	 * @param $url
	 * @param array $data
	 * @return Result
	 */
	private function send($url, array $data)
	{
		$result = new Result();

		$http = new Main\Web\HttpClient(array('disableSslVerification' => true));
		$response = $http->post($url, $this->encode($data));

		if ($response !== false)
		{
			try
			{
				$response = $this->decode($response);
				if (!is_array($response))
					$response = array();

				$response['http_code'] = $http->getStatus();
				$result->addData($response);
			}
			catch (Main\ArgumentException $e)
			{
				$result->addError(new Main\Error($e->getMessage()));
			}
		}
		else
		{
			$error = $http->getError();
			foreach ($error as $code =>$message)
			{
				$result->addError(new Main\Error($message, $code));
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
	public static function validateSettings($data)
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
	 * @return string
	 */
	private function getAccessToken()
	{
		return Main\Config\Option::get('sale', $this->getOptionName(), '');
	}

	/**
	 * @param $token
	 */
	private function setToken($token)
	{
		Main\Config\Option::set('sale', $this->getOptionName(), $token);
	}

	/**
	 * @return string
	 */
	private function getOptionName()
	{
		return static::TOKEN_OPTION_NAME.'_'.ToLower($this->getField('NUMBER_KKM'));
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

	/**
	 * @return string
	 */
	private function requestAccessToken()
	{
		$url = static::SERVICE_URL.'/getToken';
		$data = array(
			'login' => $this->getValueFromSettings('AUTH', 'LOGIN'),
			'pass' => $this->getValueFromSettings('AUTH', 'PASS')
		);

		$result = $this->send($url, $data);
		if ($result->isSuccess())
		{
			$response = $result->getData();
			$this->setToken($response['token']);

			return $response['token'];
		}

		return '';
	}

	/**
	 * @param $errorCode
	 * @throws Main\NotImplementedException
	 * @return int
	 */
	protected static function getErrorType($errorCode)
	{
		return Errors\Error::TYPE;
	}
}
