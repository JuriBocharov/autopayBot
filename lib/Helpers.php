<?php

namespace NPF;

use DateInterval;
use DateTime;

class Helpers
{
    /*
 * Данные для исправления ошибки возникшей при первых запусках бота
 * (до разработки от Креатив) убрать нельзя, так как некоторые АП все еще исполняются
 *
 * UF_CLIENTID_FOND => UF_CLIENTID_BANK,UF_GUID_AP
 */
    protected $clientIDReplasement = [
        '999-9003-000039132' => [8968, 'E7175FB8-C8DF-4114-A855-7055228D73D5'],
    ];

    /*
     * Статусы при которых автоплатеж всегда отключается
     */
    protected $deactivateStatus = [
        -2012,  // Операция не поддерживается
        -2011,  // 3d secure( платеж без подтверждения запрещен)
        -2006,  // 3d secure
        -2000,  // Карта в черном списке
        1,      // Транзакция без подтверждения личности запрещена
        100,    // Запрещены интернет-транзакции
        111,    // неверный номер карты
        120,    // Данный тип транзакции невозможен для данной карты
        208,    // Карта утеряна
        902,     // Транзакция запрещена
        101,     //Операция отклонена
    ];

    /*
     * Статусы при которых делается пропуск ошибки
     * УСТАРЕЛО: заменено списком обязательного отключения
     */
    protected $skipStatus = [
        0,      //Approved
        121,    //Decline. Excds wdrwl limt
        -2010,  //Mismatching of XID
        123,    //Decline. Excds wdrwl ltmt
        209,    //Decline. Card limitations exceeded
        902,    //Decline. Invalid trans
        903,    //Decline. Re-enter trans
        116,    //Decline. Re-enter trans
    ];

    /**
     * Делает проверку на наличие ошибки с clientID и возвращает нужное значение.
     *
     * @param $contractNumber
     * @param $autopayGUID
     *
     * @return mixed
     */
    public function getClientIdReplacement($contractNumber, $autopayGUID)
    {
        $return = $contractNumber;

        if (isset($this->clientIDReplasement[$contractNumber]) && ($this->clientIDReplasement[1] == $autopayGUID)) {
            $clientId = $this->clientIDReplasement[0];
        }

        return $return;
    }

    /**
     * Определяет нужно отключить автоплатеж или нет
     *
     * @param $actionCode - код статуса ответа
     *
     * @return bool true/false : true - отключать, false - не отключать
     */
    public function getDeactivateStatus($actionCode)
    {
        $return = false;
        if (in_array($actionCode, $this->deactivateStatus)) {
            $return = true;
        }

        return $return;
    }

    /**
     * Вычисляет время до которого будет активна регистрация.
     *
     * @return string
     */
    public function getExpirationDate()
    {
        $date = (new DateTime())->add(new DateInterval('P1D'));

        return $date->format(DATE_ISO8601);
    }
}
