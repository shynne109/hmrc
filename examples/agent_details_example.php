<?php

/**
 * AgentDetails Usage Example
 * 
 * This example demonstrates how to create and use AgentDetails with SenderDetails
 */

use HMRC\PAYE\AgentDetails;
use HMRC\PAYE\SenderDetails;

// ============================================================================
// Example 1: Create AgentDetails using constructor with array
// ============================================================================

$agentData = [
    'AgentID' => 'AGT123456',
    'Company' => 'Agent Company Ltd',
    'Address' => [
        'Line' => [
            '123 High Street',
            'London'
        ],
        'PostCode' => 'SW1A 1AA',
        'Country' => 'GB'
    ],
    'Email' => [
        'contact@agentcompany.com',
        'support@agentcompany.com'
    ],
    'Telephone' => [
        ['Number' => '020 1234 5678'],
        ['Number' => '020 1234 5679'],
        ['Number' => '020 1234 5680']
    ]
];

$agent = new AgentDetails($agentData);

// ============================================================================
// Example 2: Create AgentDetails using fluent interface
// ============================================================================

$agent2 = new AgentDetails();
$agent2
    ->setAgentId('AGT789012')
    ->setCompany('Another Agent Co')
    ->setAddress([
        'Line' => ['456 Main Street', 'Manchester'],
        'PostCode' => 'M1 1AA',
        'Country' => 'GB'
    ])
    ->addEmail('primary@anotherAgent.com')
    ->addEmail('secondary@anotherAgent.com')
    ->addTelephone('0161 1234 5678')
    ->addTelephone('0161 1234 5679');

// ============================================================================
// Example 3: Use AgentDetails with SenderDetails
// ============================================================================

$senderDetails = new SenderDetails(
    senderId: 'SENDERID',
    password: 'password',
    email: 'sender@company.com'
);

// Attach agent details
$senderDetails->setAgentDetails($agent);

// Retrieve agent details
$agentInfo = $senderDetails->getAgentDetails();
if ($agentInfo) {
    echo "Agent Company: " . $agentInfo->getCompany() . "\n";
    echo "Agent ID: " . $agentInfo->getAgentId() . "\n";
    echo "Emails: " . implode(', ', $agentInfo->getEmails()) . "\n";
}

// ============================================================================
// Example 4: Convert to array for XML serialization
// ============================================================================

$agentArray = $agent->toArray();
// Output:
// [
//     'AgentID' => 'AGT123456',
//     'Company' => 'Agent Company Ltd',
//     'Address' => [...],
//     'Email' => [... list of emails],
//     'Telephone' => [... list of phones]
// ]

echo "Agent Details as Array:\n";
print_r($agentArray);
