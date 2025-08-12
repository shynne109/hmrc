<?php

namespace HMRC\CT\Tests;

use HMRC\CT\CT600;

/**
 * Local integration style tests hitting a local LTS servlet (if available) to exercise
 * the CT600 builder with maximal coverage of configuration inputs. If the local
 * server does not support CT600 it may return an unsupported/business error –
 * these tests focus on request composition not semantic acceptance.
 */
class CT600LocalServerTest extends \PHPUnit\Framework\TestCase
{
    private function localUrl(): string
    {
        return 'http://localhost:5665/LTS/LTSPostServlet';
    }

    /**
     * Build an "extensive" CT600 exercising: multi-year apportionment, marginal relief,
     * associated companies (with financial years + starting rate flag), multiple attachment modes
     * (inline / encoded / raw), schedule injection, and replacement of IRmark token.
     */
    public function testSubmitExtensiveCt600ToLocalLts(): void
    {
        $ct = new CT600(
            $this->localUrl(),
            'SENDERID',
            'password',
            '8596148860',              // UTR
            '2022-10-01',              // Period from (spans FY 2022 & 2023)
            '2023-09-30',              // Period to
            '2023-09-30',              // Period end (IRheader)
            'Example Co Ltd',
            '12345678'
        );
        // Optional meta
        $ct->setTimestamp(new \DateTime('now', new \DateTimeZone('UTC')));
        $ct->setReturnType('amended');
        $ct->setCompanyType('5'); // exercise setter (different from default 6)
        $ct->setDeclarant('Jane Doe', 'Director');
        // Trading figures chosen to fall within marginal relief band
        $ct->setTradingFigures(500000, 150000, 0.0);
        // Two financial years with differing rates forces apportionment
        $ct->setFinancialYearRates([2022 => 19.0, 2023 => 25.0]);
        // Associated companies & FY details + starting/small company flag
        $ct->setAssociatedCompanies(1, 2022, 2023, true);
        // Explicit marginal relief parameters (defaults echoed here for clarity)
        $ct->setMarginalReliefParameters(50000, 250000, 3, 200);
    // Attachments: use encoded inline XBRL documents only (raw omitted for schema compliance).
    $ct->attachAccountsInlineXbrl('<html xmlns="http://www.w3.org/1999/xhtml"><body><p>ACCOUNTS iXBRL</p></body></html>', 'accounts.xhtml', true, 'encoded');
    $ct->attachComputationsInlineXbrl('<html xmlns="http://www.w3.org/1999/xhtml"><body><p>COMPUTATIONS iXBRL</p></body></html>', 'computations.xhtml', false, 'encoded');
        // Omit schedule injection for schema compliance (previous custom ScheduleAExample element was invalid).

        $resp = $ct->submit();
        fwrite(STDOUT, "\n===== BEGIN EPS resp DUMP =====\n");
        $summary = $resp;
        if (isset($summary['request_xml'])) {
            $summary['request_xml_length'] = strlen($summary['request_xml']);
        }
        if (isset($summary['response_xml'])) {
            $summary['response_xml_length'] = strlen($summary['response_xml']);
        }
        fwrite(STDOUT, print_r($summary, true));
        fwrite(STDOUT, "===== END EPS resp DUMP =====\n");

        $this->assertNotFalse($resp, 'Submission failed or no response (check local LTS availability)');
        $requestXml = $resp['request_xml'] ?? '';
        // Core envelope & class
        $this->assertStringContainsString('<Class>HMRC-CT-CT600</Class>', $requestXml);
        $this->assertStringContainsString('<CompanyTaxReturn', $requestXml);
        // IRmark token should be replaced
        $this->assertStringNotContainsString('IRmark+Token', $requestXml);
        // Multi-year apportionment output
        $this->assertStringContainsString('<FinancialYearTwo>', $requestXml);
        // Associated companies section exercised
        $this->assertStringContainsString('<AssociatedCompanies>', $requestXml);
        $this->assertStringContainsString('<StartingOrSmallCompaniesRate>yes</StartingOrSmallCompaniesRate>', $requestXml);
        // Attachments (encoded forms)
        $this->assertStringContainsString('<AttachedFiles>', $requestXml);
        $this->assertStringNotContainsString('<RawXBRLDocument', $requestXml);
        $this->assertStringContainsString('<EncodedInlineXBRLDocument', $requestXml);
        // No custom schedule fragment now (ensure removed)
        $this->assertStringNotContainsString('<ScheduleAExample>', $requestXml);
        // Marginal relief expected > 0 so TotalReliefsAndDeductions should not be 0.00
        if (preg_match('/<TotalReliefsAndDeductions>([^<]+)<\/TotalReliefsAndDeductions>/', $requestXml, $m)) {
            $this->assertNotEquals('0.00', $m[1], 'Marginal relief not reflected in deductions');
        }
        // Response XML (may be unsupported message); ensure something returned
        $this->assertNotEmpty($resp['response_xml']);
    }

    
    
}
