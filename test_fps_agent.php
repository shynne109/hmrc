<?php
require 'vendor/autoload.php';

use HMRC\PAYE\FPS;
use HMRC\PAYE\ReportingCompany;
use HMRC\PAYE\AgentDetails;

// Create a reporting company
$company = new ReportingCompany();
$company->setTaxOfficeNumber('123');
$company->setTaxOfficeReference('ABC123');

// Create FPS instance
$fps = new FPS(
    'test-sender-id',
    'test-password',
    $company,
    true // test mode
);

// Create and set agent details
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
$fps->setAgentDetails($agent);

// Test the getter
$retrievedAgent = $fps->getAgentDetails();
echo "Agent Details set successfully!\n";
echo "Agent ID: " . $retrievedAgent->getAgentId() . "\n";
echo "Company: " . $retrievedAgent->getCompany() . "\n";
echo "Has Data: " . ($retrievedAgent->hasData() ? "Yes" : "No") . "\n";
echo "\nAgent support added to FPS class successfully!";
