<?php
require 'vendor/autoload.php';

use HMRC\PAYE\ContactDetails;

echo "Testing ContactDetails with XSD-Compliant Name Structure\n";
echo "=========================================================\n\n";

// Test 1: Using setNameComponents
echo "Test 1: Set Name Using Components\n";
echo "----------------------------------\n";
$contact1 = new ContactDetails();
$contact1->setNameComponents('Smith', 'John', 'Mr')
    ->addEmail('john@example.com', 'work', true)
    ->addTelephone('020-1234-5678', 'work', false, true);

$name1 = $contact1->getName();
echo "Title: " . ($name1['Ttl'] ?? 'N/A') . "\n";
echo "Forename(s): " . implode(', ', $name1['Fore']) . "\n";
echo "Surname: " . $name1['Sur'] . "\n";
echo "Has Data: " . ($contact1->hasData() ? "Yes" : "No") . "\n";

// Test 2: Using setName with array (two forenames)
echo "\n\nTest 2: Set Name with Multiple Forenames\n";
echo "-----------------------------------------\n";
$contact2 = new ContactDetails();
$contact2->setNameComponents('Johnson', ['Mary', 'Elizabeth'], 'Mrs')
    ->addEmail('mary@example.com', 'home')
    ->addTelephone('07700-900-123', 'mobile', true);

$name2 = $contact2->getName();
echo "Title: " . ($name2['Ttl'] ?? 'N/A') . "\n";
echo "Forename(s): " . implode(', ', $name2['Fore']) . "\n";
echo "Surname: " . $name2['Sur'] . "\n";

// Test 3: Using setName directly
echo "\n\nTest 3: Set Name with Complete Array\n";
echo "-------------------------------------\n";
$contact3 = new ContactDetails();
$contact3->setName([
    'Ttl' => 'Dr',
    'Fore' => ['James', 'Robert'],
    'Sur' => 'Wilson'
])
->addFax('020-9876-5432', 'work');

$name3 = $contact3->getName();
echo "Title: " . ($name3['Ttl'] ?? 'N/A') . "\n";
echo "Forename(s): " . implode(', ', $name3['Fore']) . "\n";
echo "Surname: " . $name3['Sur'] . "\n";

// Test 4: Name without title
echo "\n\nTest 4: Name Without Title\n";
echo "----------------------------\n";
$contact4 = new ContactDetails();
$contact4->setNameComponents('Brown', 'Sarah');

$name4 = $contact4->getName();
echo "Title: " . ($name4['Ttl'] ?? 'N/A') . "\n";
echo "Forename(s): " . implode(', ', $name4['Fore']) . "\n";
echo "Surname: " . $name4['Sur'] . "\n";

// Test 5: Constructor with Name structure
echo "\n\nTest 5: Constructor with Name Structure\n";
echo "----------------------------------------\n";
$contactData = [
    'Name' => [
        'Ttl' => 'Ms',
        'Fore' => ['Jennifer'],
        'Sur' => 'Davis'
    ],
    'Email' => [
        ['value' => 'jennifer@work.com', 'Type' => 'work', 'Preferred' => 'yes']
    ],
    'Telephone' => [
        ['Number' => '020-5555-1234', 'Type' => 'work', 'Preferred' => 'yes']
    ]
];

$contact5 = new ContactDetails($contactData);
$name5 = $contact5->getName();
echo "Title: " . ($name5['Ttl'] ?? 'N/A') . "\n";
echo "Forename(s): " . implode(', ', $name5['Fore']) . "\n";
echo "Surname: " . $name5['Sur'] . "\n";

// Test 6: toArray() output
echo "\n\nTest 6: Full toArray() Output\n";
echo "------------------------------\n";
$array = $contact1->toArray();
print_r($array);

// Test 7: Verify Name structure in toArray
echo "\n\nTest 7: Name Structure in toArray()\n";
echo "------------------------------------\n";
if (isset($array['Name'])) {
    echo "Name is present in array\n";
    echo "Name structure:\n";
    echo "  Ttl: " . ($array['Name']['Ttl'] ?? 'not set') . "\n";
    echo "  Fore: " . (is_array($array['Name']['Fore']) ? implode(', ', $array['Name']['Fore']) : 'not set') . "\n";
    echo "  Sur: " . ($array['Name']['Sur'] ?? 'not set') . "\n";
}

echo "\n✅ All ContactDetails Name structure tests completed successfully!\n";
