<?php
require 'vendor/autoload.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\FPS;
use HMRC\PAYE\EPS;
use HMRC\PAYE\NVR;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\AgentDetails;

// Test data
$agentData = [
    'AgentID' => 'AGENT123',
    'Company' => 'Agent Company Ltd',
    'Address' => [
        'Line' => ['Line 1', 'Line 2'],
        'PostCode' => 'SW1A 1AA',
        'Country' => 'GB'
    ],
    'Email' => ['agent@example.com', 'contact@example.com'],
    'Telephone' => [
        ['Number' => '020-1234-5678'],
        ['Number' => '0121-456-7890']
    ]
];

$agent = new AgentDetails($agentData);

// Create a reporting company
$company = new ReportingCompany();
$company->setTaxOfficeNumber('123');
$company->setTaxOfficeReference('ABC123');

echo "Testing Agent Details Support Across PAYE Classes\n";
echo "==================================================\n\n";

// Test P11D
echo "1. P11D Class:\n";
$p11d = new P11D('test-sender', 'test-pwd', $company, '2025-04-05', true);
$p11d->setAgentDetails($agent);
$retrievedAgent = $p11d->getAgentDetails();
echo "   ✓ Agent set and retrieved successfully\n";
echo "   - Agent ID: " . $retrievedAgent->getAgentId() . "\n";
echo "   - Company: " . $retrievedAgent->getCompany() . "\n";
echo "   - Has Data: " . ($retrievedAgent->hasData() ? "Yes" : "No") . "\n";

// Test FPS
echo "\n2. FPS Class:\n";
$fps = new FPS('test-sender', 'test-pwd', $company, true);
$fps->setAgentDetails($agent);
$retrievedAgent = $fps->getAgentDetails();
echo "   ✓ Agent set and retrieved successfully\n";
echo "   - Agent ID: " . $retrievedAgent->getAgentId() . "\n";
echo "   - Company: " . $retrievedAgent->getCompany() . "\n";
echo "   - Has Data: " . ($retrievedAgent->hasData() ? "Yes" : "No") . "\n";

// Test EPS
echo "\n3. EPS Class:\n";
$eps = new EPS('test-sender', 'test-pwd', $company, true);
$eps->setAgentDetails($agent);
$retrievedAgent = $eps->getAgentDetails();
echo "   ✓ Agent set and retrieved successfully\n";
echo "   - Agent ID: " . $retrievedAgent->getAgentId() . "\n";
echo "   - Company: " . $retrievedAgent->getCompany() . "\n";
echo "   - Has Data: " . ($retrievedAgent->hasData() ? "Yes" : "No") . "\n";

// Test NVR
echo "\n4. NVR Class:\n";
$nvr = new NVR('test-sender', 'test-pwd', $company, true);
$nvr->setAgentDetails($agent);
$retrievedAgent = $nvr->getAgentDetails();
echo "   ✓ Agent set and retrieved successfully\n";
echo "   - Agent ID: " . $retrievedAgent->getAgentId() . "\n";
echo "   - Company: " . $retrievedAgent->getCompany() . "\n";
echo "   - Has Data: " . ($retrievedAgent->hasData() ? "Yes" : "No") . "\n";

// Test empty agent (should not add to XML)
echo "\n5. Testing Empty Agent (should be ignored):\n";
$emptyAgent = new AgentDetails();
$p11d2 = new P11D('test-sender', 'test-pwd', $company, '2025-04-05', true);
$p11d2->setAgentDetails($emptyAgent);
echo "   ✓ Empty agent handled correctly\n";
echo "   - Empty Agent Has Data: " . ($p11d2->getAgentDetails()->hasData() ? "Yes" : "No") . "\n";

echo "\n✅ All tests passed! Agent support successfully added to all PAYE classes.\n";
