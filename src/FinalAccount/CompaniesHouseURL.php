<?php

namespace HMRC\FinalAccount;

/**
 * Companies House API URLs for API Filing services
 */
abstract class CompaniesHouseURL
{
    /** @var string URL of sandbox environment for Companies House */
    public const SANDBOX_API = 'https://api-sandbox.company-information.service.gov.uk';

    /** @var string URL of live environment for Companies House */
    public const LIVE_API = 'https://api.company-information.service.gov.uk';

    /** @var string URL of sandbox identity/OAuth service */
    public const SANDBOX_IDENTITY = 'https://identity-sandbox.company-information.service.gov.uk';

    /** @var string URL of live identity/OAuth service */
    public const LIVE_IDENTITY = 'https://identity.company-information.service.gov.uk';

    /** @var string URL of sandbox test data API */
    public const SANDBOX_TEST_DATA = 'https://test-data-sandbox.company-information.service.gov.uk';
}
