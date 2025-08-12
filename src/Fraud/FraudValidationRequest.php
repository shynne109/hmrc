<?php

namespace HMRC\Fraud;

use HMRC\Request\RequestHeader;
use HMRC\Request\RequestMethod;
use HMRC\Request\RequestHeaderValue;
use HMRC\Request\RequestWithServerToken;
use HMRC\GovernmentTestScenario\GovernmentTestScenario;
use HMRC\HTTP\Header;
use HMRC\Request\RequestWithAccessToken;


class FraudValidationRequest extends RequestWithAccessToken
{


    protected function getMethod(): string
    {
        return RequestMethod::GET;
    }


    protected function getApiPath(): string
    {
        return '/test/fraud-prevention-headers/validate';
    }


}
