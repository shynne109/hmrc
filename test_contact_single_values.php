<?php
require 'vendor/autoload.php';

use HMRC\PAYE\ContactDetails;

echo "Testing ContactDetails with Single String Values\n";
echo "=================================================\n\n";

// Test 1: Using setters
echo "Test 1: Set Contact Details Using Setters\n";
echo "------------------------------------------\n";
$contact1 = new ContactDetails();
$contact1->setNameComponents('Smith', 'John', 'Mr')
    ->setEmail('john.smith@example.com')
    ->setTelephone('020-1234-5678')
    ->setFax('020-1234-9999');

$name1 = $contact1->getName();
echo "Name:\n";
echo "  Title: " . ($name1['Ttl'] ?? 'N/A') . "\n";
echo "  Forename(s): " . implode(', ', $name1['Fore']) . "\n";
echo "  Surname: " . $name1['Sur'] . "\n";
echo "Email: " . ($contact1->getEmail() ?? 'Not set') . "\n";
echo "Telephone: " . ($contact1->getTelephone() ?? 'Not set') . "\n";
echo "Fax: " . ($contact1->getFax() ?? 'Not set') . "\n";
echo "Has Data: " . ($contact1->hasData() ? "Yes" : "No") . "\n";

// Test 2: Using constructor
echo "\n\nTest 2: Using Constructor\n";
echo "-------------------------\n";
$contactData = [
    'Name' => [
        'Ttl' => 'Mrs',
        'Fore' => ['Mary'],
        'Sur' => 'Johnson'
    ],
    'Email' => 'mary.johnson@example.com',
    'Telephone' => '07700-900-123',
    'Fax' => '020-9876-5432'
];

$contact2 = new ContactDetails($contactData);
$name2 = $contact2->getName();
echo "Name:\n";
echo "  Title: " . ($name2['Ttl'] ?? 'N/A') . "\n";
echo "  Forename(s): " . implode(', ', $name2['Fore']) . "\n";
echo "  Surname: " . $name2['Sur'] . "\n";
echo "Email: " . ($contact2->getEmail() ?? 'Not set') . "\n";
echo "Telephone: " . ($contact2->getTelephone() ?? 'Not set') . "\n";
echo "Fax: " . ($contact2->getFax() ?? 'Not set') . "\n";

// Test 3: toArray() output
echo "\n\nTest 3: toArray() Output\n";
echo "------------------------\n";
$array = $contact1->toArray();
print_r($array);

// Test 4: Partial data
echo "\n\nTest 4: Partial Contact Data (No Fax)\n";
echo "--------------------------------------\n";
$contact3 = new ContactDetails();
$contact3->setNameComponents('Brown', 'Sarah')
    ->setEmail('sarah.brown@example.com');

echo "Email: " . ($contact3->getEmail() ?? 'Not set') . "\n";
echo "Telephone: " . ($contact3->getTelephone() ?? 'Not set') . "\n";
echo "Fax: " . ($contact3->getFax() ?? 'Not set') . "\n";
echo "Has Data: " . ($contact3->hasData() ? "Yes" : "No") . "\n";

// Test 5: Empty contact
echo "\n\nTest 5: Empty Contact\n";
echo "---------------------\n";
$emptyContact = new ContactDetails();
echo "Has Data: " . ($emptyContact->hasData() ? "Yes" : "No") . "\n";
echo "Email: " . ($emptyContact->getEmail() ?? 'Not set') . "\n";
echo "Telephone: " . ($emptyContact->getTelephone() ?? 'Not set') . "\n";
echo "Fax: " . ($emptyContact->getFax() ?? 'Not set') . "\n";

// Test 6: Verify single values in array output
echo "\n\nTest 6: Verify Single Values in toArray()\n";
echo "------------------------------------------\n";
$arrayOutput = $contact2->toArray();
echo "Email type: " . gettype($arrayOutput['Email']) . "\n";
echo "Email value: " . $arrayOutput['Email'] . "\n";
echo "Telephone type: " . gettype($arrayOutput['Telephone']) . "\n";
echo "Telephone value: " . $arrayOutput['Telephone'] . "\n";
echo "Fax type: " . gettype($arrayOutput['Fax']) . "\n";
echo "Fax value: " . $arrayOutput['Fax'] . "\n";

echo "\n✅ All ContactDetails single string tests completed successfully!\n";
