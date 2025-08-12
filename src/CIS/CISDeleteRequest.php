<?php

namespace HMRC\CIS;

use HMRC\Request\RequestMethod;

abstract class CISDeleteRequest extends CISRequest
{
    
    protected function getMethod(): string
    {
        return RequestMethod::DELETE;
    }

    /**
     * @return array
     */
    abstract protected function getQueryString(): array;
}
