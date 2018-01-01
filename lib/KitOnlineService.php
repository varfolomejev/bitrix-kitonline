<?php

namespace Varfolomejev\Sale;

use Bitrix\Main\Type\DateTime;

class KitOnlineService
{
	public function prepareCheckRequest($data)
	{

	}
}

$class = new KitOnlineService();
$class->prepareCheckRequest(array(
	'type' => 'sell',
	'unique_id' => 3,
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
	'date_create' => new DateTime('2017-12-31 14:33:33.000000'),
	'payments' => array(
		array(
			'is_cash' => 'N',
			'sum' => 336
		)
	),
	'client_email' => 'test@te.te',
	'client_phone' => '123132165',
	'total_sum' => 336
));