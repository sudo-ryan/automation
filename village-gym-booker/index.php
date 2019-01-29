<?php

require 'vendor/autoload.php';

use Goutte\Client;
$client = new Client();

$username = '';
$password = '';

$classesRequested = [
    "Monday" => [
        [
            'name' => 'Body Combat',
            'time' => '17:00 - 17:30'
        ]   
    ],
    "Tuesday" => [
        [
            'name' => 'Burn',
            'time' => '19:30 - 20:00'
        ],
        [
            'name' => 'Virtual RPM',
            'time' => '20:00 - 20:30'
        ]
    ]
];

// 1. LOGIN =================================================================

$crawler = $client->request('GET', 'https://www.villagegym.co.uk/member');
$form = $crawler->selectButton('Log in')->form();
$form['EmailAddress'] = $username;
$form['Password'] = $password;
$crawler = $client->submit($form);

// 2. NAVIGATE TO NEXT WEEK =================================================

$link = $crawler->selectLink('All days')->link();
$crawler = $client->click($link);

// 3. LOOP OVER CLASSES =================================================

$crawler->filter('.tab-pane')->each(function ($day) use ($classesRequested, $crawler, $client) {

    // formatted day of the week
    $formattedDate = (new DateTime(str_replace('/', '-', $day->attr('data-day'))));
    
    // check you have a class on this day
    if (!empty($classesRequested[$formattedDate->format('l')])) {
        print '*** Checking classes for ' . $formattedDate->format('l') . " ***\n";
        $day->filter('dl > .item.available')->each(function ($class) use ($formattedDate, $classesRequested, $crawler, $client) {
            foreach ($classesRequested[$formattedDate->format('l')] as $classRequested) {

                $classTime = $class->filter('.time')->text();
                $className = $class->attr('data-classtype');
                $classBtn = $class->filter('dd > .status > .btn');

                // skip classes where we cant find a date / time / btn
                if ($classBtn->count() == 0 || !isset($classTime, $className)) {
                    continue;
                }

                $classId = $classBtn->attr('data-classid');
                $gymId = $classBtn->attr('data-gymid');

                if ($className == $classRequested['name'] && $classTime == $classRequested['time']) {
                    
                    print '> Found requested class ' . $classRequested['name'] . ', trying to book...' . "\n";
                    
                    // lets skip the modal and just hit their API direct with their 'security' token...
                    $securityTokenInput = $crawler->filter('input[name="__RequestVerificationToken"]')->getNode(0);

                    if (is_null($securityTokenInput)) {
                        print 'Security token empty, skipping...';
                        continue;
                    }

                    print '✓ Booking class...' . "\n";

                    // make request against their internal api used on website
                    $client->request('POST', 'https://www.villagegym.co.uk/member/classtimetable/BookOrCancel', [
                        'bookClass' => 'Confirm Booking',
                        'SelectedBookingID' => 0,
                        'SelectedGymId' => $gymId,
                        'SelectedClassId' => $classId,
                        'SelectedClassDate' => $formattedDate->format('Y-m-d'),
                        '__RequestVerificationToken' => $securityTokenInput->getAttribute('value')
                    ]);
                }
            }
        });
    } else {
        print 'Skipping ' . $formattedDate->format('l') . "\n";
    }
});