<?php
/**
 * Validate P46 Car XML against HMRC EXB-2025 Schema
 * Run this script to check for schema violations
 */

// Enable DOM error handling
libxml_use_internal_errors(true);

// The XML you submitted (extracted IRenvelope portion only)
$xmlString = <<<XML
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
  <IRmark Type="generic">ohBfl6cHdqyBPP64tX3eZSWs674=</IRmark>
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
    <DateFirstAvailable>2024-05-30</DateFirstAvailable>
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

// Path to the XSD schema
$xsdPath = __DIR__ . '/src/PAYE/P11D/EXB-2025-v1-0 (2).xsd';

echo "P46 Car XML Schema Validation\n";
echo "==============================\n\n";

// Create DOMDocument
$dom = new DOMDocument();
$dom->loadXML($xmlString);

// Validate against XSD
if ($dom->schemaValidate($xsdPath)) {
    echo "✅ XML is VALID according to the XSD schema!\n";
} else {
    echo "❌ XML is INVALID. Errors found:\n\n";
    
    $errors = libxml_get_errors();
    foreach ($errors as $error) {
        $errorLevel = match($error->level) {
            LIBXML_ERR_WARNING => 'Warning',
            LIBXML_ERR_ERROR => 'Error',
            LIBXML_ERR_FATAL => 'Fatal',
            default => 'Unknown'
        };
        
        echo "[$errorLevel] Line {$error->line}, Column {$error->column}: {$error->message}\n";
    }
    
    libxml_clear_errors();
}

echo "\n\n";
echo "Additional Pattern Checks:\n";
echo "==========================\n";

// Manual pattern checks for common issues
$simpleXml = simplexml_load_string($xmlString);
$simpleXml->registerXPathNamespace('exb', 'http://www.govtalk.gov.uk/taxation/EXB/24-25/1');

// Check NINO pattern
$nino = (string)$simpleXml->xpath('//exb:NINO')[0] ?? '';
$ninoPattern = '/^[A-Z]{2}[0-9]{6}[A-D ]$/';
echo "NINO: '$nino' - Length: " . strlen($nino) . " - Pattern match: " . (preg_match($ninoPattern, $nino) ? 'YES ✅' : 'NO ❌') . "\n";

// Check monetary patterns
$carPrice = (string)$simpleXml->xpath('//exb:CarPrice')[0] ?? '';
$monetaryPattern = '/^[0-9]+\.00$/';
echo "CarPrice: '$carPrice' - Pattern match: " . (preg_match($monetaryPattern, $carPrice) ? 'YES ✅' : 'NO ❌') . "\n";

$capitalContributions = (string)$simpleXml->xpath('//exb:CapitalContributions')[0] ?? '';
echo "CapitalContributions: '$capitalContributions' - Pattern match: " . (preg_match($monetaryPattern, $capitalContributions) ? 'YES ✅' : 'NO ❌') . "\n";

$privateUsePayment = (string)$simpleXml->xpath('//exb:PrivateUsePayment')[0] ?? '';
echo "PrivateUsePayment: '$privateUsePayment' - Pattern match: " . (preg_match($monetaryPattern, $privateUsePayment) ? 'YES ✅' : 'NO ❌') . "\n";

// Check forename pattern
$forePattern = '/^[A-Za-z][A-Za-z\'\-]*$/';
$forenames = $simpleXml->xpath('//exb:P46Car//exb:Fore');
foreach ($forenames as $fore) {
    $foreVal = (string)$fore;
    echo "Fore: '$foreVal' - Pattern match: " . (preg_match($forePattern, $foreVal) ? 'YES ✅' : 'NO ❌') . "\n";
}

// Check surname pattern
$surPattern = '/^[A-Za-z][A-Za-z0-9 ,\.\(\)\/&\-\']*$/';
$surnames = $simpleXml->xpath('//exb:P46Car//exb:Sur');
foreach ($surnames as $sur) {
    $surVal = (string)$sur;
    echo "Sur: '$surVal' - Pattern match: " . (preg_match($surPattern, $surVal) ? 'YES ✅' : 'NO ❌') . "\n";
}

// Check title pattern  
$ttlPattern = '/^[A-Za-z][A-Za-z\'\-]*$/';
$titles = $simpleXml->xpath('//exb:P46Car//exb:Ttl');
foreach ($titles as $ttl) {
    $ttlVal = (string)$ttl;
    echo "Ttl: '$ttlVal' - Pattern match: " . (preg_match($ttlPattern, $ttlVal) ? 'YES ✅' : 'NO ❌') . "\n";
}

echo "\n\nData Logic Checks:\n";
echo "==================\n";

// Check FuelType + ZeroEmissionMileage logic
$fuelType = (string)$simpleXml->xpath('//exb:FuelType')[0] ?? '';
$zeroEmissionMileage = (string)$simpleXml->xpath('//exb:ZeroEmissionMileage')[0] ?? '';
echo "FuelType: '$fuelType'\n";
echo "ZeroEmissionMileage: '$zeroEmissionMileage'\n";

if ($fuelType === 'A' && !empty($zeroEmissionMileage)) {
    echo "⚠️  WARNING: ZeroEmissionMileage is typically only for hybrid/electric vehicles.\n";
    echo "   FuelType 'A' = 'All other' (includes petrol). Having ZeroEmissionMileage\n";
    echo "   suggests this might be a hybrid vehicle, which could be correct.\n";
    echo "   However, if this is a regular petrol car, remove ZeroEmissionMileage.\n";
}

// Check CO2 Emissions value for electric vehicles
$emissions = (string)$simpleXml->xpath('//exb:Emissions')[0] ?? '';
echo "\nCO2 Emissions: $emissions g/km\n";
if ((int)$emissions === 0) {
    echo "   This appears to be a zero-emission (electric) vehicle.\n";
} elseif ((int)$emissions <= 50) {
    echo "   This appears to be an ultra-low emission vehicle (ULEV).\n";
}
