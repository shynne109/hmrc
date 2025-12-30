<?php
/**
 * Test exact XML from submission against XSD
 */

libxml_use_internal_errors(true);

$xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<IRenvelope xmlns="http://www.govtalk.gov.uk/taxation/EXB/24-25/1">
 <IRheader>
  <Keys>
   <Key Type="TaxOfficeNumber">635</Key>
   <Key Type="TaxOfficeReference">A635</Key>
  </Keys>
  <PeriodEnd>2025-04-05</PeriodEnd>
  <Principal>
   <Contact>
    <Name>
     <Ttl>Mr</Ttl>
     <Fore>John</Fore>
     <Sur>O'Dare</Sur>
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
     <Fore>Jane</Fore>
     <Sur>Smith</Sur>
    </Name>
   </Contact>
  </Agent>
  <DefaultCurrency>GBP</DefaultCurrency>
  <IRmark Type="generic">7FK92SxCbpOz4M8/il2mhE17+O0=</IRmark>
  <Sender>Employer</Sender>
 </IRheader>
 <ExpensesAndBenefits>
  <Employer>
   <Name>LARGE COMPANY &amp; CO</Name>
  </Employer>
  <Declarations>
   <P11Dincluded>are not due</P11Dincluded>
   <P46CarDeclaration>yes</P46CarDeclaration>
  </Declarations>
  <P11DrecordCount>0</P11DrecordCount>
  <P46CarRecordCount>1</P46CarRecordCount>
  <P46Car>
   <EmployeeDetails>
    <Name>
     <Ttl>MR</Ttl>
     <Fore>GEORGE</Fore>
     <Fore>EDGAR</Fore>
     <Sur>TURNER</Sur>
    </Name>
    <NINO>RN000012 </NINO>
   </EmployeeDetails>
   <SubmissionReason>
    <ProvidedCar>yes</ProvidedCar>
   </SubmissionReason>
   <CarDetails>
    <MakeAndModel>Citroen C4 LX</MakeAndModel>
    <EngineSize Category="1">1200</EngineSize>
    <DateFirstRegistered>2022-02-12</DateFirstRegistered>
    <FuelType>A</FuelType>
   </CarDetails>
   <CO2Emissions>
    <Emissions>47</Emissions>
    <ZeroEmissionMileage>65</ZeroEmissionMileage>
   </CO2Emissions>
   <MonetaryDetails>
    <CarPrice>13200.00</CarPrice>
    <AccessoriesPrice>500.00</AccessoriesPrice>
    <DateFirstAvailable>2025-05-30</DateFirstAvailable>
    <CapitalContributions>230.00</CapitalContributions>
    <PrivateUsePayment Interval="Y">320.00</PrivateUsePayment>
   </MonetaryDetails>
   <Fuel>
    <PrivateUse>yes</PrivateUse>
    <FuelPaidByEmployee>yes</FuelPaidByEmployee>
   </Fuel>
  </P46Car>
 </ExpensesAndBenefits>
</IRenvelope>
XML;

$dom = new DOMDocument();
$dom->loadXML($xml);

echo "XSD Validation:\n";
echo "===============\n";
if ($dom->schemaValidate(__DIR__ . '/src/PAYE/P11D/EXB-2025-v1-0 (2).xsd')) {
    echo "✅ XML is VALID according to XSD\n";
} else {
    echo "❌ XML ERRORS:\n";
    foreach (libxml_get_errors() as $error) {
        echo "Line {$error->line}: {$error->message}";
    }
}

echo "\n\nPotential Issues Check:\n";
echo "=======================\n";

// Check for whitespace issues in NINO
$simpleXml = simplexml_load_string($xml);
$simpleXml->registerXPathNamespace('exb', 'http://www.govtalk.gov.uk/taxation/EXB/24-25/1');

$nino = $simpleXml->xpath('//exb:NINO')[0] ?? '';
$ninoStr = (string)$nino;
echo "NINO value: '$ninoStr'\n";
echo "NINO length: " . strlen($ninoStr) . "\n";
echo "NINO hex: " . bin2hex($ninoStr) . "\n";

// Check if trailing space is actually a space character (0x20)
$lastChar = substr($ninoStr, -1);
echo "Last char hex: " . bin2hex($lastChar) . " (should be 20 for space)\n";

// Check the employer name
$employerName = $simpleXml->xpath('//exb:Employer/exb:Name')[0] ?? '';
echo "\nEmployer Name: '" . (string)$employerName . "'\n";

// Check for any non-printable characters in the XML
echo "\nScanning for non-printable characters...\n";
preg_match_all('/[^\x20-\x7E\x0A\x0D\x09]/', $xml, $matches);
if (!empty($matches[0])) {
    echo "Found non-printable characters: ";
    foreach ($matches[0] as $char) {
        echo "0x" . bin2hex($char) . " ";
    }
    echo "\n";
} else {
    echo "No non-printable characters found (excluding common ones like £, &, etc.)\n";
}

// Check contact details structure
echo "\nContact Details Analysis:\n";
$principalContact = $simpleXml->xpath('//exb:Principal/exb:Contact')[0] ?? null;
if ($principalContact) {
    echo "Principal Contact found\n";
    $name = $principalContact->xpath('exb:Name')[0] ?? null;
    if ($name) {
        $ttl = $name->xpath('exb:Ttl')[0] ?? '';
        $fore = $name->xpath('exb:Fore');
        $sur = $name->xpath('exb:Sur')[0] ?? '';
        echo "  Title: '" . (string)$ttl . "'\n";
        echo "  Fore count: " . count($fore) . "\n";
        foreach ($fore as $i => $f) {
            echo "    Fore[$i]: '" . (string)$f . "'\n";
        }
        echo "  Sur: '" . (string)$sur . "'\n";
    }
}

// Check Agent Contact 
$agentContact = $simpleXml->xpath('//exb:Agent/exb:Contact')[0] ?? null;
if ($agentContact) {
    echo "\nAgent Contact found\n";
    $name = $agentContact->xpath('exb:Name')[0] ?? null;
    if ($name) {
        $ttl = $name->xpath('exb:Ttl')[0] ?? '';
        $fore = $name->xpath('exb:Fore');
        $sur = $name->xpath('exb:Sur')[0] ?? '';
        echo "  Title: '" . (string)$ttl . "' (should be empty per XSD Contact)\n";
        echo "  Fore count: " . count($fore) . " (should be 1-2)\n";
        foreach ($fore as $i => $f) {
            echo "    Fore[$i]: '" . (string)$f . "'\n";
        }
        echo "  Sur: '" . (string)$sur . "'\n";
    }
}
