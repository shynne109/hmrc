<?php

namespace HMRC\Hello;


use HMRC\Request\RequestMethod;
use HMRC\Request\RequestWithServerToken;

class HelloAvailableServiceRequest extends RequestWithServerToken
{
    protected function getMethod(): string
    {
        return RequestMethod::GET;
    }

    protected function getApiPath(): string
    {
        return '/create-test-user/services';
    }
}
