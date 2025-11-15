<?php
require 'vendor/autoload.php';

use HMRC\PAYE\AgentDetails;

// Test with the provided agent data
$agentData = [
    'AgentID' => 'string',
    'Company' => 'string',
    'Address' => [
        'Line' => 'g',
        'PostCode' => 'Q',
        'Country' => 'sN#~'
    ],
    'Email' => [
        '??7@9!6?0!?q6?  ',
        '`&amp;=7@0=vfa!'
    ],
    'Telephone' => [
        ['Number' => '0s'],
        ['Number' => '2-sss-ss))s-'],
        ['Number' => 's'],
        ['Number' => '))9-6((']
    ]
];

$agent = new AgentDetails($agentData);

// Test hasData() method
echo "Agent has data: " . ($agent->hasData() ? "Yes" : "No") . "\n";
echo "\nAgent Details:\n";
echo "Agent ID: " . ($agent->getAgentId() ?? 'null') . "\n";
echo "Company: " . ($agent->getCompany() ?? 'null') . "\n";
echo "Address: " . json_encode($agent->getAddress()) . "\n";
echo "Emails: " . json_encode($agent->getEmails()) . "\n";
echo "Telephones: " . json_encode($agent->getTelephones()) . "\n";

// Test with empty agent
$emptyAgent = new AgentDetails();
echo "\n\nEmpty agent has data: " . ($emptyAgent->hasData() ? "Yes" : "No") . "\n";
