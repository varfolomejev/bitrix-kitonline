<?php

namespace Varfolomejev\Sale;

IncludeModuleLangFile(__FILE__);

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
				"Price" => $this->toPenies($item['price']),
				"Quantity" => $item['quantity'],
				"SubjectName" => $item['name'],
				"Tax" => (int)$tax,
			);
		}

		$request = array(
			"Request" => $this->prepareRequestData($kitonline),
			"Check" => array(
				"CheckId" => "100" . $data['unique_id'],
				"CalculationType" => $checkType,
				"Sum" => $this->toPenies($data['total_sum']),
				"Email" => $data['client_email'],
				"TaxSystemType" => (int)$kitonline->getValueFromSettings('AUTH', 'TAX_SYSTEM_TYPE'),
				"Pay" => array(
					"EMoneySum" => $this->toPenies($data['total_sum']),
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
			"Sign" => md5($companyId . $password . $requestId),
            "RequestSource" => GetMessage("VARFOLOMEJEV_KITONLINE_MODULE_BITRIX")
		);
	}

	private function generateUID()
	{
		return (int)date('YmdHis');
	}

	private function toPenies($sum)
    {
        return (float)$sum * 100;
    }
}