<?php
/**
 * Test FPS with ChannelRouting Timestamp to fix BVR 7831.
 * Uses proper C14N-based IRmark calculation matching the library.
 */

$endpoint = 'https://test-transaction-engine.tax.service.gov.uk/submission';
$pollEndpoint = 'https://test-transaction-engine.tax.service.gov.uk/poll';
$password = '12qwaszx34ERDFCV56tyghbn78UIJKM %*AAA,./llll@kaa[_}-qwerty=poiuytrewqLKJHGFDSA\ZZZ#p9876?_=PPPbvcxz;qz:aa6+54hahgbcvsi{gg(g)0O.b';

function submitAndPoll($xml, $endpoint, $pollEndpoint, $password, $label) {
    echo "=== Test: $label ===\n";
    
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=UTF-8'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if (!preg_match('/<CorrelationID>([^<]+)</', $response, $m)) {
        echo "  No correlation ID - envelope rejected\n";
        preg_match_all('/<Text>([^<]+)</', $response, $texts);
        foreach ($texts[1] as $t) echo "  $t\n";
        echo "\n";
        return;
    }
    
    $corrId = $m[1];
    echo "  Correlation: $corrId - Polling...\n";
    sleep(10);
    
    $pollXml = '<?xml version="1.0" encoding="UTF-8"?>
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope">
 <EnvelopeVersion>2.0</EnvelopeVersion>
 <Header><MessageDetails>
   <Class>HMRC-PAYE-RTI-FPS</Class><Qualifier>poll</Qualifier><Function>submit</Function>
   <CorrelationID>' . $corrId . '</CorrelationID>
   <Transformation>XML</Transformation><GatewayTest>1</GatewayTest>
  </MessageDetails>
  <SenderDetails><IDAuthentication><SenderID>ISV635</SenderID>
   <Authentication><Method>clear</Method><Role>principal</Role>
   <Value>' . $password . '</Value></Authentication>
  </IDAuthentication></SenderDetails></Header>
 <GovTalkDetails><ChannelRouting><Channel><URI>9256</URI>
  <Product>Abbpay Solutions</Product><Version>1.0.0</Version>
 </Channel></ChannelRouting></GovTalkDetails><Body/></GovTalkMessage>';
    
    $ch2 = curl_init($pollEndpoint);
    curl_setopt_array($ch2, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $pollXml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=UTF-8'],
        CURLOPT_TIMEOUT => 30,
    ]);
    $pollResponse = curl_exec($ch2);
    curl_close($ch2);
    
    if (preg_match('/<Qualifier>response</', $pollResponse)) {
        echo "  *** SUCCESS ***\n\n";
    } else {
        preg_match_all('/<Number>(\d+)</', $pollResponse, $nums);
        preg_match_all('/<Text>([^<]+)</', $pollResponse, $texts);
        preg_match_all('/<Location>([^<]+)</', $pollResponse, $locs);
        for ($i = 0; $i < count($nums[1]); $i++) {
            $loc = $locs[1][$i] ?? '';
            echo "  Error {$nums[1][$i]}: {$texts[1][$i]}" . ($loc ? " [$loc]" : "") . "\n";
        }
        echo "  *** FAILED ***\n\n";
    }
}

// Build the body XML (IRenvelope) with IRmark placeholder
$bodyInner = '<IRenvelope xmlns="http://www.govtalk.gov.uk/taxation/PAYE/RTI/FullPaymentSubmission/25-26/1">
 <IRheader>
  <Keys>
   <Key Type="TaxOfficeNumber">635</Key>
   <Key Type="TaxOfficeReference">A635</Key>
  </Keys>
  <PeriodEnd>2026-02-07</PeriodEnd>
  <Principal>
   <Contact>
    <Name>
     <Fore>John</Fore>
     <Sur>O\'Dare</Sur>
    </Name>
    <Telephone>
     <Number>0113 4960242</Number>
    </Telephone>
   </Contact>
  </Principal>
  <Agent>
   <AgentID>AX321</AgentID>
   <Company>Agents Are Us</Company>
   <Address>
    <Line>12 Daffodil Road East Benton</Line>
    <Line>Bradford</Line>
    <PostCode>BD12 1XX</PostCode>
   </Address>
   <Contact>
    <Name>
     <Fore>Mary</Fore>
     <Sur>Jane Smith</Sur>
    </Name>
   </Contact>
  </Agent>
  <DefaultCurrency>GBP</DefaultCurrency>
  <IRmark Type="generic">IRmark+Token</IRmark>
  <Sender>Employer</Sender>
 </IRheader>
 <FullPaymentSubmission>
  <EmpRefs>
   <OfficeNo>635</OfficeNo>
   <PayeRef>A635</PayeRef>
   <AORef>120PR03012191</AORef>
   <COTAXRef>9255858485</COTAXRef>
  </EmpRefs>
  <RelatedTaxYear>25-26</RelatedTaxYear>
  <Employee>
   <EmployeeDetails>
    <NINO>RN000003C</NINO>
    <Name>
     <Ttl>Mrs</Ttl>
     <Fore>Blodwin</Fore>
     <Sur>Wales</Sur>
    </Name>
    <Address>
     <Line>10 Swansea Crescent</Line>
     <Line>Cardiff</Line>
     <UKPostcode>CF1 2AB</UKPostcode>
    </Address>
    <BirthDate>1981-04-12</BirthDate>
    <Gender>F</Gender>
   </EmployeeDetails>
   <Employment>
    <Starter>
     <StartDate>2026-03-06</StartDate>
     <StartDec>B</StartDec>
    </Starter>
    <PayId>TAX3</PayId>
    <FiguresToDate>
     <TaxablePay>2000.00</TaxablePay>
     <TotalTax>190.20</TotalTax>
     <StudentLoansTD>0.00</StudentLoansTD>
     <PostgradLoansTD>0.00</PostgradLoansTD>
     <BenefitsTaxedViaPayrollYTD>0.00</BenefitsTaxedViaPayrollYTD>
     <EmpeePenContribnsPaidYTD>0.00</EmpeePenContribnsPaidYTD>
    </FiguresToDate>
    <Payment>
     <PayFreq>M1</PayFreq>
     <PmtDate>2026-04-05</PmtDate>
     <MonthNo>12</MonthNo>
     <PeriodsCovered>1</PeriodsCovered>
     <HoursWorked>D</HoursWorked>
     <TaxCode BasisNonCumulative="yes" TaxRegime="C">1257L</TaxCode>
     <TaxablePay>2000.00</TaxablePay>
     <PayAfterStatDedns>1733.64</PayAfterStatDedns>
     <TaxDeductedOrRefunded>190.20</TaxDeductedOrRefunded>
    </Payment>
    <NIlettersAndValues>
     <NIletter>A</NIletter>
     <GrossEarningsForNICsInPd>2000.00</GrossEarningsForNICsInPd>
     <GrossEarningsForNICsYTD>2000.00</GrossEarningsForNICsYTD>
     <AtLELYTD>533.00</AtLELYTD>
     <LELtoPTYTD>515.00</LELtoPTYTD>
     <PTtoUELYTD>952.00</PTtoUELYTD>
     <TotalEmpNICInPd>313.61</TotalEmpNICInPd>
     <TotalEmpNICYTD>313.61</TotalEmpNICYTD>
     <EmpeeContribnsInPd>76.16</EmpeeContribnsInPd>
     <EmpeeContribnsYTD>76.16</EmpeeContribnsYTD>
    </NIlettersAndValues>
   </Employment>
  </Employee>
  <Employee>
   <EmployeeDetails>
    <NINO>RN000001A</NINO>
    <Name>
     <Ttl>Mr</Ttl>
     <Fore>Jimmy</Fore>
     <Sur>Restof-Uk</Sur>
    </Name>
    <Address>
     <Line>1 Tax Test Road</Line>
     <Line>PAYE Town</Line>
     <UKPostcode>BN1 1YZ</UKPostcode>
    </Address>
    <BirthDate>1989-08-14</BirthDate>
    <Gender>M</Gender>
   </EmployeeDetails>
   <Employment>
    <PayId>TAX1</PayId>
    <LeavingDate>2026-04-05</LeavingDate>
    <FiguresToDate>
     <TaxablePay>47800.00</TaxablePay>
     <TotalTax>7044.60</TotalTax>
     <StudentLoansTD>0.00</StudentLoansTD>
     <PostgradLoansTD>0.00</PostgradLoansTD>
     <BenefitsTaxedViaPayrollYTD>2400.00</BenefitsTaxedViaPayrollYTD>
     <EmpeePenContribnsPaidYTD>0.00</EmpeePenContribnsPaidYTD>
    </FiguresToDate>
    <Payment>
     <PayFreq>M1</PayFreq>
     <PmtDate>2026-04-05</PmtDate>
     <MonthNo>12</MonthNo>
     <PeriodsCovered>1</PeriodsCovered>
     <HoursWorked>D</HoursWorked>
     <TaxCode>1257L</TaxCode>
     <TaxablePay>26900.00</TaxablePay>
     <PayAfterStatDedns>21676.96</PayAfterStatDedns>
     <BenefitsTaxedViaPayroll>200.00</BenefitsTaxedViaPayroll>
     <Class1ANICsYTD>8638.70</Class1ANICsYTD>
     <TaxDeductedOrRefunded>5170.40</TaxDeductedOrRefunded>
    </Payment>
    <NIlettersAndValues>
     <NIletter>A</NIletter>
     <GrossEarningsForNICsInPd>1700.00</GrossEarningsForNICsInPd>
     <GrossEarningsForNICsYTD>20400.00</GrossEarningsForNICsYTD>
     <AtLELYTD>6396.00</AtLELYTD>
     <LELtoPTYTD>6180.00</LELtoPTYTD>
     <PTtoUELYTD>7824.00</PTtoUELYTD>
     <TotalEmpNICInPd>245.09</TotalEmpNICInPd>
     <TotalEmpNICYTD>2935.80</TotalEmpNICYTD>
     <EmpeeContribnsInPd>52.64</EmpeeContribnsInPd>
     <EmpeeContribnsYTD>626.40</EmpeeContribnsYTD>
    </NIlettersAndValues>
   </Employment>
  </Employee>
  <Employee>
   <EmployeeDetails>
    <NINO>RN000002B</NINO>
    <Name>
     <Ttl>Miss</Ttl>
     <Fore>Michelle</Fore>
     <Fore>Mary</Fore>
     <Sur>O\'Scot</Sur>
    </Name>
    <Address>
     <Line>1 Glasgow Road</Line>
     <Line>The Highlands &amp; Islands</Line>
     <UKPostcode>KY16 8BT</UKPostcode>
    </Address>
    <BirthDate>1993-04-15</BirthDate>
    <Gender>F</Gender>
   </EmployeeDetails>
   <Employment>
    <PayId>TAX2</PayId>
    <FiguresToDate>
     <TaxablePay>72000.00</TaxablePay>
     <TotalTax>18250.86</TotalTax>
     <StudentLoansTD>3528.00</StudentLoansTD>
     <PostgradLoansTD>0.00</PostgradLoansTD>
     <BenefitsTaxedViaPayrollYTD>0.00</BenefitsTaxedViaPayrollYTD>
     <EmpeePenContribnsPaidYTD>0.00</EmpeePenContribnsPaidYTD>
    </FiguresToDate>
    <Payment>
     <PayFreq>M1</PayFreq>
     <PmtDate>2026-04-05</PmtDate>
     <MonthNo>12</MonthNo>
     <PeriodsCovered>1</PeriodsCovered>
     <HoursWorked>C</HoursWorked>
     <TaxCode TaxRegime="S">1257L</TaxCode>
     <TaxablePay>6000.00</TaxablePay>
     <PayAfterStatDedns>3896.96</PayAfterStatDedns>
     <StudentLoanRecovered PlanType="04">294.00</StudentLoanRecovered>
     <TaxDeductedOrRefunded>1520.94</TaxDeductedOrRefunded>
    </Payment>
    <NIlettersAndValues>
     <NIletter>A</NIletter>
     <GrossEarningsForNICsInPd>6000.00</GrossEarningsForNICsInPd>
     <GrossEarningsForNICsYTD>72000.00</GrossEarningsForNICsYTD>
     <AtLELYTD>6396.00</AtLELYTD>
     <LELtoPTYTD>6180.00</LELtoPTYTD>
     <PTtoUELYTD>37692.00</PTtoUELYTD>
     <TotalEmpNICInPd>1125.55</TotalEmpNICInPd>
     <TotalEmpNICYTD>13500.00</TotalEmpNICYTD>
     <EmpeeContribnsInPd>288.10</EmpeeContribnsInPd>
     <EmpeeContribnsYTD>3450.60</EmpeeContribnsYTD>
    </NIlettersAndValues>
   </Employment>
  </Employee>
  <FinalSubmission><ForYear>yes</ForYear></FinalSubmission>
 </FullPaymentSubmission>
</IRenvelope>';

// Build full envelope first with IRmark placeholder
$xml1 = '<?xml version="1.0" encoding="UTF-8"?>
<GovTalkMessage xmlns="http://www.govtalk.gov.uk/CM/envelope">
 <EnvelopeVersion>2.0</EnvelopeVersion>
 <Header>
  <MessageDetails>
   <Class>HMRC-PAYE-RTI-FPS</Class>
   <Qualifier>request</Qualifier>
   <Function>submit</Function>
   <CorrelationID></CorrelationID>
   <Transformation>XML</Transformation>
   <GatewayTest>1</GatewayTest>
   <GatewayTimestamp></GatewayTimestamp>
  </MessageDetails>
  <SenderDetails>
   <IDAuthentication>
    <SenderID>ISV635</SenderID>
    <Authentication>
     <Method>clear</Method>
     <Role>principal</Role>
     <Value>' . $password . '</Value>
    </Authentication>
   </IDAuthentication>
  </SenderDetails>
 </Header>
 <GovTalkDetails>
  <Keys>
   <Key Type="TaxOfficeNumber">635</Key>
   <Key Type="TaxOfficeReference">A635</Key>
  </Keys>
  <TargetDetails>
   <Organisation>IR</Organisation>
  </TargetDetails>
  <ChannelRouting>
   <Channel>
    <URI>9256</URI>
    <Product>Abbpay Solutions</Product>
    <Version>1.0.0</Version>
   </Channel>
   <Timestamp>2026-03-20T12:00:00</Timestamp>
  </ChannelRouting>
 </GovTalkDetails>
 <Body>' . $bodyInner . '</Body>
</GovTalkMessage>';

// Now replicate the library's exact packageDigest() + generateIRMark() process:
// Step 1: Load through SimpleXML as the library does
$sxml = simplexml_load_string($xml1);
$namespaces = $sxml->getNamespaces();
echo "Namespaces: " . print_r($namespaces, true);

// Step 2: Extract body via regex on asXML() output, exactly as the library does
$asXml = $sxml->asXML();
preg_match('#<Body>(.*)<\/Body>#su', $asXml, $bodyMatches);
$packageBody = $bodyMatches[1];

// Step 3: Strip IRmark element
$hashInput = preg_replace('/<(vat:)?IRmark Type="generic">[A-Za-z0-9\/\+=]*<\/(vat:)?IRmark>/', '', $packageBody, -1, $matchCount);
echo "IRmark elements stripped: $matchCount\n";

// Step 4: Wrap with Body + namespaces (exactly as library does)
$nsStrings = [];
foreach ($namespaces as $key => $value) {
    if ($key !== '') {
        $nsStrings[] = 'xmlns:' . $key . '="' . $value . '"';
    } else {
        $nsStrings[] = 'xmlns="' . $value . '"';
    }
}
$bodyCompiled = '<Body ' . implode(' ', $nsStrings) . '>' . $hashInput . '</Body>';

// Step 5: Load into DOM, C14N, hash
$dom = new DOMDocument();
$dom->loadXML($bodyCompiled);
$irmark = base64_encode(sha1($dom->documentElement->C14N(), true));
echo "Calculated IRmark: $irmark\n";

// Step 6: Replace placeholder in original XML
$xml1 = str_replace('IRmark+Token', $irmark, $xml1);

submitAndPoll($xml1, $endpoint, $pollEndpoint, $password, 'FPS + Timestamp=2026-03-20 + proper IRmark');
