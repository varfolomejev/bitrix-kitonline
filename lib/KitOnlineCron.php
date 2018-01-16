<?php

use Bitrix\Sale\Cashbox\Internals\CashboxCheckTable;
use Bitrix\Sale\Cashbox\Internals\CashboxTable;
use Varfolomejev\Sale\Kitonline;

class KitOnlineCron
{
    /**
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     */
    public static function checkCashBoxCheck()
    {
        if(CModule::IncludeModule('sale')){
            $cashBoxes = array();
            $cashBoxData = CashboxTable::getList(array(
                'select' => array('ID', 'SETTINGS'),
                'filter' => array('HANDLER' => '\\\\Varfolomejev\\\\Sale\\\\Kitonline')
            ));

            while($cashBox = $cashBoxData->fetch()){
                $cashBoxes[$cashBox['ID']] = array(
                    'ID' => $cashBox['ID'],
                    'SETTINGS' => $cashBox['SETTINGS']
                );
            }

            if($cashBoxes){
                $checkData = CashboxCheckTable::getList(
                    array(
                        'select' => array('ID', 'CASHBOX_ID', 'EXTERNAL_UUID', 'TYPE'),
                        'filter' => array(
                            'CASHBOX_ID' => array_keys($cashBoxes),
                            'STATUS' => 'P',
                        ),
                        'limit' => 10
                    )
                );
                while($check = $checkData->fetch())
                {
                    sleep(2); //need sleep, because we will be banned
                    $check['SETTINGS'] = $cashBoxes[$check['CASHBOX_ID']]['SETTINGS'];
                    try {
                        Kitonline::applyCheckResult($check);
                    } catch(\Exception $exception){
                        @file_put_contents(dirname(__FILE__) . '/error.txt', $exception->getTraceAsString());
                    }

                }
            }
        }
        return 'KitOnlineCron::checkCashBoxCheck();';
    }
}