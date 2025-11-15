<?php
require 'vendor/autoload.php';

use HMRC\PAYE\P11D;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\AgentDetails;

// Setup
$company = new ReportingCompany();
$company->setTaxOfficeNumber('123');
$company->setTaxOfficeReference('ABC123');

$agentData = [
    'AgentID' => 'AGENT001',
    'Company' => 'Test Agent Company',
    'Address' => [
        'Line' => 'Test Address',
        'PostCode' => 'AB12 3CD',
        'Country' => 'UK'
    ],
    'Email' => ['test@agent.com'],
    'Telephone' => [
        ['Number' => '020-7946-0958']
    ]
];

$agent = new AgentDetails($agentData);

// Create P11D with agent
$p11d = new P11D('test-sender', 'test-pwd', $company, '2025-04-05', true);
$p11d->setAgentDetails($agent);

// Build XML
$xml = $p11d->buildXML();

// Check if agent information is present
if (strpos($xml, '<Agent>') !== false) {
    echo "✅ Agent XML element found in output\n";
    
    // Extract agent section
    if (preg_match('/<Agent>.*?<\/Agent>/s', $xml, $matches)) {
        echo "\nAgent XML Section:\n";
        echo "==================\n";
        echo $matches[0] . "\n";
    }
    
    // Verify key elements
    $checks = [
        '<AgentID>AGENT001</AgentID>' => 'Agent ID',
        '<Company>Test Agent Company</Company>' => 'Company Name',
        '<Country>UK</Country>' => 'Country',
        '<PostCode>AB12 3CD</PostCode>' => 'Post Code',
        '<Email>test@agent.com</Email>' => 'Email',
        '<Number>020-7946-0958</Number>' => 'Telephone'
    ];
    
    echo "\nElement Validation:\n";
    echo "===================\n";
    foreach ($checks as $element => $description) {
        if (strpos($xml, $element) !== false) {
            echo "✓ $description: Present\n";
        } else {
            echo "✗ $description: Missing\n";
        }
    }
} else {
    echo "❌ Agent XML element NOT found in output\n";
}

// Test with empty agent (should not include Agent element)
echo "\n\nTesting Empty Agent:\n";
echo "====================\n";

$emptyAgent = new AgentDetails();
$p11d2 = new P11D('test-sender', 'test-pwd', $company, '2025-04-05', true);
$p11d2->setAgentDetails($emptyAgent);

$xml2 = $p11d2->buildXML();

if (strpos($xml2, '<Agent>') === false) {
    echo "✅ Empty Agent correctly excluded from XML output\n";
} else {
    echo "❌ Empty Agent should not be in XML output\n";
}
