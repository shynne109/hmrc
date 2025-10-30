<?php

namespace HMRC\FinalAccount;

/**
 * Companies House API Filing OAuth2 Scopes
 */
class FilingScope
{
    /** @var string Base URL for Companies House identity service */
    const IDENTITY_BASE = 'https://identity.company-information.service.gov.uk';

    /** @var string Base URL for Companies House API */
    const API_BASE = 'https://api.company-information.service.gov.uk';

    /** @var string Required scope to read user profile (required for all API Filing) */
    const PROFILE_READ = self::IDENTITY_BASE . '/user/profile.read';

    /**
     * Get the scope for updating a company's registered office address
     *
     * @param string $companyNumber The company number (e.g., '00000001')
     * @return string The ROA update scope
     */
    public static function registeredOfficeAddress(string $companyNumber): string
    {
        return self::API_BASE . "/company/{$companyNumber}/registered-office-address.update";
    }

    /**
     * Get the scope for insolvency write access
     * Note: This scope can only be granted to clients registered as insolvency software
     *
     * @return string The insolvency write scope
     */
    public static function insolvency(): string
    {
        return self::API_BASE . '/company/*/insolvency.write-full';
    }

    /**
     * Get the scope for updating a company's registered email address
     *
     * @param string $companyNumber The company number (e.g., '00000001')
     * @return string The REA update scope
     */
    public static function registeredEmailAddress(string $companyNumber): string
    {
        return self::API_BASE . "/company/{$companyNumber}/registered-email-address.update";
    }

    /**
     * Build a complete scope string for ROA filing
     *
     * @param string $companyNumber The company number
     * @return string Space-separated scope string
     */
    public static function roaFiling(string $companyNumber): string
    {
        return self::PROFILE_READ . ' ' . self::registeredOfficeAddress($companyNumber);
    }

    /**
     * Build a complete scope string for Insolvency filing
     *
     * @return string Space-separated scope string
     */
    public static function insolvencyFiling(): string
    {
        return self::PROFILE_READ . ' ' . self::insolvency();
    }

    /**
     * Build a complete scope string for REA filing
     *
     * @param string $companyNumber The company number
     * @return string Space-separated scope string
     */
    public static function reaFiling(string $companyNumber): string
    {
        return self::PROFILE_READ . ' ' . self::registeredEmailAddress($companyNumber);
    }

    /**
     * Build a custom scope string from multiple scopes
     * Automatically includes the required PROFILE_READ scope
     *
     * @param array $scopes Array of scope strings
     * @return string Space-separated scope string
     */
    public static function custom(array $scopes): string
    {
        // Always include profile read
        $allScopes = [self::PROFILE_READ];
        
        // Add additional scopes, avoiding duplicates
        foreach ($scopes as $scope) {
            if (!in_array($scope, $allScopes)) {
                $allScopes[] = $scope;
            }
        }

        return implode(' ', $allScopes);
    }

    /**
     * Build scopes for multiple company filings
     * Useful when filing for multiple companies in one authorization
     *
     * @param array $companyNumbers Array of company numbers
     * @param string $filingType Type of filing: 'roa', 'rea', or 'both'
     * @return string Space-separated scope string
     */
    public static function multiCompany(array $companyNumbers, string $filingType = 'both'): string
    {
        $scopes = [self::PROFILE_READ];

        foreach ($companyNumbers as $companyNumber) {
            if ($filingType === 'roa' || $filingType === 'both') {
                $scopes[] = self::registeredOfficeAddress($companyNumber);
            }
            if ($filingType === 'rea' || $filingType === 'both') {
                $scopes[] = self::registeredEmailAddress($companyNumber);
            }
        }

        return implode(' ', array_unique($scopes));
    }
}
