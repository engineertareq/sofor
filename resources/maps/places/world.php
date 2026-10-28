<?php

/*
|--------------------------------------------------------------------------
| World: words in a trip that point to a country
|--------------------------------------------------------------------------
|
| Country names and their common variants come from the map data already.
| This adds the cities and trips people write instead, weighted towards
| where travellers from Bangladesh go. Value: an ISO 3166-1 alpha-2 code.
|
*/

return [
    // India
    'kolkata' => 'IN', 'calcutta' => 'IN', 'delhi' => 'IN', 'new delhi' => 'IN', 'agra' => 'IN', 'taj mahal' => 'IN',
    'darjeeling' => 'IN', 'sikkim' => 'IN', 'gangtok' => 'IN', 'shillong' => 'IN', 'meghalaya' => 'IN',
    'cherrapunji' => 'IN', 'guwahati' => 'IN', 'agartala' => 'IN', 'kashmir' => 'IN', 'srinagar' => 'IN',
    'manali' => 'IN', 'shimla' => 'IN', 'goa' => 'IN', 'mumbai' => 'IN', 'chennai' => 'IN', 'bangalore' => 'IN',
    'bengaluru' => 'IN', 'jaipur' => 'IN', 'rajasthan' => 'IN', 'kerala' => 'IN', 'ajmer' => 'IN',
    'varanasi' => 'IN', 'siliguri' => 'IN', 'kalimpong' => 'IN', 'vellore' => 'IN', 'ladakh' => 'IN',
    'কলকাতা' => 'IN', 'দার্জিলিং' => 'IN', 'ভারত' => 'IN',

    // Nepal, Bhutan, Sri Lanka, Maldives
    'kathmandu' => 'NP', 'pokhara' => 'NP', 'nagarkot' => 'NP', 'chitwan' => 'NP', 'everest' => 'NP', 'annapurna' => 'NP',
    'নেপাল' => 'NP',
    'thimphu' => 'BT', 'paro' => 'BT', 'punakha' => 'BT', 'phuentsholing' => 'BT', 'ভুটান' => 'BT',
    'colombo' => 'LK', 'kandy' => 'LK', 'sigiriya' => 'LK', 'galle' => 'LK',
    // Not "Malé": it reads as "male" once accents are dropped.
    'maafushi' => 'MV',

    // South East and East Asia
    'bangkok' => 'TH', 'phuket' => 'TH', 'pattaya' => 'TH', 'chiang mai' => 'TH', 'krabi' => 'TH', 'koh samui' => 'TH',
    'phi phi' => 'TH', 'থাইল্যান্ড' => 'TH',
    'kuala lumpur' => 'MY', 'langkawi' => 'MY', 'penang' => 'MY', 'genting' => 'MY', 'malacca' => 'MY', 'melaka' => 'MY',
    'মালয়েশিয়া' => 'MY',
    'সিঙ্গাপুর' => 'SG',
    'bali' => 'ID', 'jakarta' => 'ID', 'lombok' => 'ID',
    'hanoi' => 'VN', 'ho chi minh' => 'VN', 'saigon' => 'VN', 'ha long' => 'VN', 'halong' => 'VN',
    'siem reap' => 'KH', 'angkor' => 'KH', 'phnom penh' => 'KH',
    'yangon' => 'MM', 'rangoon' => 'MM',
    'beijing' => 'CN', 'shanghai' => 'CN', 'guangzhou' => 'CN', 'kunming' => 'CN',
    'tokyo' => 'JP', 'kyoto' => 'JP', 'osaka' => 'JP', 'জাপান' => 'JP',
    'seoul' => 'KR', 'busan' => 'KR',

    // Middle East and Türkiye
    'dubai' => 'AE', 'abu dhabi' => 'AE', 'sharjah' => 'AE', 'দুবাই' => 'AE',
    'makkah' => 'SA', 'mecca' => 'SA', 'madinah' => 'SA', 'medina' => 'SA', 'jeddah' => 'SA', 'riyadh' => 'SA',
    // Bangla matches inside words, so short ones like হজ (inside সহজ) are left out.
    'umrah' => 'SA', 'hajj' => 'SA', 'মক্কা' => 'SA', 'মদিনা' => 'SA', 'ওমরাহ' => 'SA',
    'doha' => 'QA', 'muscat' => 'OM',
    'istanbul' => 'TR', 'cappadocia' => 'TR', 'antalya' => 'TR',
    'cairo' => 'EG', 'giza' => 'EG',

    // Further afield
    'london' => 'GB', 'manchester' => 'GB', 'edinburgh' => 'GB', 'লন্ডন' => 'GB',
    'paris' => 'FR', 'rome' => 'IT', 'venice' => 'IT', 'milan' => 'IT', 'amsterdam' => 'NL', 'barcelona' => 'ES',
    'madrid' => 'ES', 'zurich' => 'CH', 'swiss alps' => 'CH', 'prague' => 'CZ', 'vienna' => 'AT', 'berlin' => 'DE',
    'new york' => 'US', 'los angeles' => 'US', 'san francisco' => 'US', 'washington dc' => 'US',
    'toronto' => 'CA', 'vancouver' => 'CA',
    'sydney' => 'AU', 'melbourne' => 'AU',
];
