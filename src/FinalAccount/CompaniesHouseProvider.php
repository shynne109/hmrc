<?php

namespace HMRC\FinalAccount;

use HMRC\Environment\Environment;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * OAuth2 Provider for Companies House API Filing
 * Extends the base OAuth2 provider to work with Companies House identity service
 */
class CompaniesHouseProvider extends GenericProvider
{
    /**
     * CompaniesHouseProvider constructor.
     *
     * @param string $clientID Client ID from Companies House Developer Hub
     * @param string $clientSecret Client Secret from Companies House Developer Hub
     * @param string $callbackURI Redirect URI registered with Companies House
     */
    public function __construct(string $clientID, string $clientSecret, string $callbackURI)
    {
        $options = array_merge([
            'clientId'     => $clientID,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $callbackURI,
        ], $this->getOptionsFromEnvironment());

        parent::__construct($options);
    }

    /**
     * Returns the string that should be used to separate scopes
     * Companies House uses space separation
     *
     * @return string Scope separator
     */
    protected function getScopeSeparator()
    {
        return ' ';
    }

    /**
     * Get OAuth URLs based on current environment (sandbox or live)
     *
     * @return array
     */
    private function getOptionsFromEnvironment(): array
    {
        $identityHost = Environment::getInstance()->isLive()
            ? CompaniesHouseURL::LIVE_IDENTITY
            : CompaniesHouseURL::SANDBOX_IDENTITY;

        return [
            'urlAuthorize'            => "{$identityHost}/oauth2/authorise",
            'urlAccessToken'          => "{$identityHost}/oauth/token",
            'urlResourceOwnerDetails' => "{$identityHost}/oauth/userinfo",
        ];
    }

    /**
     * Redirect user to Companies House authorization URL
     *
     * @param array|string $scopes Scopes to request (array or space-separated string)
     * @return void
     */
    public function redirectToAuthorizationURL($scopes)
    {
        if (is_string($scopes)) {
            $scopes = explode(' ', $scopes);
        }

        $authorizationUrl = $this->getAuthorizationUrl([
            'scope' => $scopes,
        ]);

        header('Location: ' . $authorizationUrl);
        exit;
    }

    /**
     * Get the Companies House authorization URL without redirecting
     *
     * @param array|string $scopes Scopes to request (array or space-separated string)
     * @return string The authorization URL
     */
    public function getRedirectAuthorizationURL($scopes): string
    {
        if (is_string($scopes)) {
            $scopes = explode(' ', $scopes);
        }

        return $this->getAuthorizationUrl([
            'scope' => $scopes,
        ]);
    }

    /**
     * Get the current state parameter (useful for CSRF protection)
     * Fixed: was recursively calling itself
     *
     * @return string
     */
    public function getState(): string
    {
        return parent::getState();
    }
}