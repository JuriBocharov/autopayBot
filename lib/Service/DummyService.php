<?php

namespace NPF\Service;

/**
 * Класс заглушка для эмуляции сервиса.
 */
class DummyService
{
    /**
     * Обновление автоплатежа.
     *
     * @param string $UserID
     * @param string $AutoPayGUID
     * @param string $PayDay
     * @param string $PayAmount
     * @param string $Periodicity
     * @param string $AutoPayStatus
     *
     * @return bool
     */
    public function ChangeAutoPay($AutoPayGUID, $PayDay, $PayAmount, $Periodicity, $AutoPayStatus)
    {
        return true;
    }

    /**
     * Получить очередь на проведение автоплатежей.
     */
    public function GetAutoPayHistList()
    {
        $currentDate = (new \DateTime())->format('d.m.Y');
        $return = [
            'AutoPayHistList' => [
                [
                    'AutoPayGUID' => '5B3A3FEB-53B6-46D1-801A-4F2A13376E06',
                    'PayDate' => $currentDate,
                    'PayAmount' => '150000.00',
                    'AutoPayHistGUID' => '01E4B296-2386-4A6B-B1D9-5FA07A351CCD',
                    'ContractNumber' => '999-9999-000724365',
                    'BindingID' => '3692cb06-491c-4d5f-a54a-0f7b1b14fffc',
                    'Periodicity' => '1', //отсутствует !!!!!!!!!!!!
                ],
                [
                    'AutoPayGUID' => '5B3A3FEB-53B6-46D1-801A-4F2B74376E06',
                    'PayDate' => $currentDate,
                    'PayAmount' => '150000.00',
                    'AutoPayHistGUID' => '01E4B296-2386-4A6B-B1D9-5FA07A351CED',
                    'ContractNumber' => '999-9999-000924825',
                    'BindingID' => '9e894cc4-1c74-49fe-b418-e9c2c372943f',
                    'Periodicity' => '1', //отсутствует !!!!!!!!!!!!
                ],
                [
                    'AutoPayGUID' => '5B3A3FEB-53B6-46D1-801A-4F2C18572D06',
                    'PayDate' => $currentDate,
                    'PayAmount' => '150000.00',
                    'AutoPayHistGUID' => '01E4B296-2386-4A6B-B1D9-5FA07A351ACD',
                    'ContractNumber' => '999-9999-000923785',
                    'BindingID' => '405a23ad-54fb-456e-88e4-489cd72807d5',
                    'Periodicity' => '1', //отсутствует !!!!!!!!!!!!
                ],
            ],
        ];

        return $return;
    }

    /**
     * Изменить статус проведения автоплатежа.
     *
     * @param $histGUID
     * @param $histStatus
     * @param $histStatusDetail
     *
     * @return mixed
     */
    public function ChangeAutoPayHist($histGUID, $histStatus, $histStatusDetail)
    {
        return true;
    }
}
