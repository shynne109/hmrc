<?php
require 'vendor/autoload.php';

use HMRC\PAYE\ContactDetails;

echo "Testing ContactDetails Class\n";
echo "============================\n\n";

// Test 1: Create contact with constructor
$data = [
    'Name' => 'John Smith',
    'Email' => [
        ['value' => 'john@example.com', 'Type' => 'work', 'Preferred' => 'yes'],
        ['value' => 'jsmith@personal.com', 'Type' => 'home']
    ],
    'Telephone' => [
        ['Number' => '020-1234-5678', 'Type' => 'work', 'Preferred' => 'yes'],
        ['Number' => '07700-900-123', 'Type' => 'mobile', 'Mobile' => 'yes']
    ],
    'Fax' => [
        ['Number' => '020-1234-9999', 'Type' => 'work']
    ]
];

$contact1 = new ContactDetails($data);

echo "Test 1: Constructor Initialization\n";
echo "-----------------------------------\n";
echo "Name: " . $contact1->getName() . "\n";
echo "Emails count: " . count($contact1->getEmails()) . "\n";
echo "Telephones count: " . count($contact1->getTelephones()) . "\n";
echo "Faxes count: " . count($contact1->getFaxes()) . "\n";
echo "Has Data: " . ($contact1->hasData() ? "Yes" : "No") . "\n";

// Test 2: Using fluent methods
echo "\n\nTest 2: Fluent API Methods\n";
echo "--------------------------\n";

$contact2 = new ContactDetails();
$contact2->setName('Jane Doe')
    ->addEmail('jane@work.com', 'work', true)
    ->addEmail('jane@home.com', 'home')
    ->addTelephone('020-9876-5432', 'work', false, true)
    ->addTelephone('07700-900-456', 'mobile', true, false)
    ->addFax('020-9876-9999', 'work');

echo "Name: " . $contact2->getName() . "\n";
echo "Has Data: " . ($contact2->hasData() ? "Yes" : "No") . "\n";

// Test 3: Display full details
echo "\n\nTest 3: Full Contact Details\n";
echo "----------------------------\n";

$array = $contact2->toArray();
print_r($array);

// Test 4: Email details
echo "\n\nTest 4: Email Details\n";
echo "---------------------\n";
foreach ($contact2->getEmails() as $i => $email) {
    echo "Email " . ($i + 1) . ":\n";
    echo "  Address: " . $email['value'] . "\n";
    if (isset($email['Type'])) {
        echo "  Type: " . $email['Type'] . "\n";
    }
    if (isset($email['Preferred'])) {
        echo "  Preferred: " . $email['Preferred'] . "\n";
    }
}

// Test 5: Telephone details
echo "\n\nTest 5: Telephone Details\n";
echo "-------------------------\n";
foreach ($contact2->getTelephones() as $i => $phone) {
    echo "Phone " . ($i + 1) . ":\n";
    echo "  Number: " . $phone['Number'] . "\n";
    if (isset($phone['Type'])) {
        echo "  Type: " . $phone['Type'] . "\n";
    }
    if (isset($phone['Mobile'])) {
        echo "  Mobile: " . $phone['Mobile'] . "\n";
    }
    if (isset($phone['Preferred'])) {
        echo "  Preferred: " . $phone['Preferred'] . "\n";
    }
}

// Test 6: Fax details
echo "\n\nTest 6: Fax Details\n";
echo "-------------------\n";
foreach ($contact2->getFaxes() as $i => $fax) {
    echo "Fax " . ($i + 1) . ":\n";
    echo "  Number: " . $fax['Number'] . "\n";
    if (isset($fax['Type'])) {
        echo "  Type: " . $fax['Type'] . "\n";
    }
}

// Test 7: Empty contact
echo "\n\nTest 7: Empty Contact\n";
echo "---------------------\n";
$emptyContact = new ContactDetails();
echo "Has Data: " . ($emptyContact->hasData() ? "Yes" : "No") . "\n";

echo "\n✅ All ContactDetails tests completed successfully!\n";
