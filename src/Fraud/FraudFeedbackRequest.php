<?php

namespace HMRC\Fraud;

use HMRC\Request\RequestHeader;
use HMRC\Request\RequestMethod;
use HMRC\Request\RequestHeaderValue;
use HMRC\Request\RequestWithServerToken;
use HMRC\GovernmentTestScenario\GovernmentTestScenario;
use HMRC\HTTP\Header;
use HMRC\Request\RequestWithAccessToken;


class FraudFeedbackRequest extends RequestWithServerToken
{



    protected function getMethod(): string
    {
        return RequestMethod::GET;
    }

    protected function getApiPath(): string
    {
        return "/test/fraud-prevention-headers/vat-mtd/validation-feedback";
    }





}

// https://test-api.service.hmrc.gov.uk/test/fraud-prevention-headers/{$this->periodKey}/validation-feedback
