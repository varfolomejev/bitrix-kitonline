<?php

namespace Varfolomejev\Sale;

use function md5;
use const PHP_INT_MAX;
use function rand;
use function time;

class KitOnlineService
{
	/**
	 * @param $kitonline
	 * @param $check
	 *
	 * @return array
	 */
	public function prepareCheckRequest($kitonline, $check)
	{
		$data = $check->getDataForCheck();
		$tax = $kitonline->getValueFromSettings('AUTH', 'TAX');
		$checkTypeMap = $kitonline->getCheckTypeMap();
		$checkType = $checkTypeMap[$check::getType()];
		$subjects = array();

		foreach ($data['items'] as $i => $item)
		{
			$subjects[] = array(
				"Price" => (float)$item['price'],
				"Quantity" => $item['quantity'],
				"SubjectName" => mb_convert_encoding($item['name'], 'UTF-8'),
				"Tax" => (int)$tax,
			);
		}

		$request = array(
			"Request" => $this->prepareRequestData($kitonline),
			"Check" => array(
				"CheckId" => "100" . $data['unique_id'],
				"CalculationType" => $checkType,
				"Sum" => (float)$data['total_sum'],
				"Email" => $data['client_email'],
				"TaxSystemType" => (int)$kitonline->getValueFromSettings('AUTH', 'TAX_SYSTEM_TYPE'),
				"Pay" => array(
					"CashSum" => (float)$data['total_sum'],
				),
				"Subjects" => $subjects
			)
		);

		return $request;
	}

	public function prepareCheckStateRequest($data, $kitonline)
	{
		return array(
			"Request" => $this->prepareRequestData($kitonline),
			"CheckQueueId" => $data['kit_online_check_queue_id']
		);
	}

	private function prepareRequestData($kitonline)
	{
		$requestId = $this->generateUID();
		$companyId = $kitonline->getValueFromSettings('AUTH', 'COMPANY_ID');
		$password = $kitonline->getValueFromSettings('AUTH', 'PASSWORD');
		return array(
			"RequestId" => (int)$requestId,
			"CompanyId" => (int)$companyId,
			"UserLogin" => $kitonline->getValueFromSettings('AUTH', 'LOGIN'),
			"Sign" => md5($companyId . $password . $requestId)
		);
	}

	private function generateUID()
	{
		return (int)date('YmdHis');
	}
}

/*
class kitonline {
	public function getValueFromSettings($type, $key)
	{
		$data = [
			'COMPANY_ID' => 1,
			'LOGIN' => 'bot0111',
			'PASSWORD' => 'bot01112017',
			'TAX_SYSTEM_TYPE' => 1,
			'TAX' => 1,
		];
		return $data[$key];
	}

	public function getCheckTypeMap()
	{
		return array(
			'sell' => 1,
		);
	}
}

class check {
	public static function getType()
	{
		return 'sell';
	}
}
*/

//$class = new KitOnlineService();

/*
$results = $class->prepareCheckRequest(array(
	'type' => 'sell',
	'unique_id' => 1000003,
	'items' => array(
		array(
			'name' => 'Туфли Ультра Лайн',
			'base_price' => 236,
			'price' => 236,
			'sum' => 236,
			'quantity' => 1,
			'vat' => 2
		),
		array(
			'name' => 'Штаны Цветочная Поляна',
			'base_price' => 100,
			'price' => 100,
			'sum' => 100,
			'quantity' => 1,
			'vat' => 1
		),
		array(
			'name' => 'Доставка',
			'base_price' => 0,
			'price' => 0,
			'sum' => 0,
			'quantity' => 1,
			'vat' => 0
		),
	),
	//'date_create' => new DateTime('2017-12-31 14:33:33.000000'),
	'payments' => array(
		array(
			'is_cash' => 'N',
			'sum' => 336
		)
	),
	'client_email' => 'test@te.te',
	'client_phone' => '123132165',
	'total_sum' => 336
), new kitonline(), new check());
*/

/*
$results = $class->prepareCheckStateRequest(array('kit_online_check_queue_id' => 2714), new kitonline());
*/

/*
echo PHP_EOL;
echo json_encode($results);
//echo json_last_error_msg();
//print_r($results);
echo PHP_EOL;
exit;
*/