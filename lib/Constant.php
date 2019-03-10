<?php

namespace NPF;

class Constant
{
    const SB_API = 'https://securepayments.sberbank.ru/payment/rest/';
    const MERCHANT_LOGIN = 'npfsb_recurent-api';
    const MERCHANT_PASSWORD = 'Password*3';

    const SB_API_TEST = 'https://3dsec.sberbank.ru/payment/rest/';
    const MERCHANT_LOGIN_TEST = 'npfsb_ssl-api';
    const MERCHANT_PASSWORD_TEST = 'npfsb_ssl';

    const NPF_WSDL = 'http://192.168.10.5/wsnpf.dll/wsdl/IIwsNPF';
    const NPF_WSDL_TEST = 'http://192.168.10.6/wsnpf.dll/wsdl/IIwsNPF';
}
