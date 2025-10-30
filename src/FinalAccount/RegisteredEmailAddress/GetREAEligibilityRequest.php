<?php

namespace HMRC\FinalAccount\RegisteredEmailAddress;

use HMRC\FinalAccount\FilingRequest;
use HMRC\Exceptions\InvalidVariableValueException;

/**
 * Get eligibility of a company for registered email address data change functionality
 * 
 * GET https://api.company-information.service.gov.uk/registered-email-address/company/{company_number}/eligibility
 * 
 * Required OAuth2 scopes:
 * - https://api.company-information.service.gov.uk/company/{company_number}/registered-email-address.update
 * - https://identity.company-information.service.gov.uk/user/profile.read
 */
class GetREAEligibilityRequest extends FilingRequest
{
    /**
     * Get the HTTP method for this request
     *
     * @return string
     */
    protected function getMethod(): string
    {
        return 'GET';
    }

    /**
     * Get the API path for this request
     *
     * @return string
     * @throws InvalidVariableValueException
     */
    protected function getApiPath(): string
    {
        if (empty($this->companyNumber)) {
            throw new InvalidVariableValueException('Company number must be set before making request');
        }

        return "/registered-email-address/company/{$this->companyNumber}/eligibility";
    }

    /**
     * Get the request body (not used for GET requests)
     *
     * @return array
     */
    protected function getRequestBody(): array
    {
        return [];
    }
}
