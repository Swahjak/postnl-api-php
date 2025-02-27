# Unofficial PostNL REST API PHP Bindings

[![Latest Stable Version](https://poser.pugx.org/firstred/postnl-api-php/v)](https://packagist.org/packages/firstred/postnl-api-php)
[![Total Downloads](https://poser.pugx.org/firstred/postnl-api-php/downloads)](https://packagist.org/packages/firstred/postnl-api-php)
[![.github/workflows/test.yaml](https://github.com/firstred/postnl-api-php/actions/workflows/test.yaml/badge.svg)](https://github.com/firstred/postnl-api-php/actions/workflows/test.yaml)
[![Documentation Status](https://readthedocs.org/projects/postnl-php/badge/?version=latest)](https://postnl-php.readthedocs.io/en/latest/?badge=latest)
[![codecov](https://codecov.io/gh/firstred/postnl-api-php/branch/master/graph/badge.svg)](https://codecov.io/gh/firstred/postnl-api-php)
[![license mit](https://poser.pugx.org/firstred/postnl-api-php/license.svg)](https://packagist.org/packages/firstred/postnl-api-php)
[![PHP Version Require](https://poser.pugx.org/firstred/postnl-api-php/require/php)](https://packagist.org/packages/firstred/postnl-api-php)

## About

This unofficial PHP library for the PostNL REST API aims to provide a simple way to connect your 
application with PostNL. By abstracting away needless complexity when processing shipment 
information and increased fault-tolerance, you can get up and running with PostNL in minutes.  
At the lower level this library uses asynchronous communication and payload splitting for 
improved performance.

### Status

| Service                                     | Status | Version |
| ------------------------------------------- |--------| ------- |
| **Addresses**                               |        |         |
| Adrescheck Nationaal                        | N/A    | N/A     |
| Adrescheck Basis Nationaal                  | N/A    | N/A     |
| Adrescheck Internationaal                   | N/A    | N/A     |
| Geo Adrescheck Nationaal                    | N/A    | N/A     |
| **Creditworthiness & Business information** |        |         |
| Bedrijfscheck Nationaal                     | N/A    | N/A     |
| Kredietcheck Zakelijk                       | N/A    | N/A     |
| **Send & Track**                            |        |         |
| Barcode webservice                          | ✓      | 1.1     |
| Labelling webservice                        | ✓      | 2.2     |
| Confirming webservice                       | ✓      | 2.0     |
| Shippingstatus webservice                   | ✓      | 2.0     |
| Shipping webservice                         | ✓      | 2.0     |
| **Delivery Options**                        |        |         |
| Deliverydate webservice                     | ✓      | 2.2     |
| Location webservice                         | ✓      | 2.1     |
| Timeframe webservice                        | ✓      | 2.1     |
| Checkout webservice                         | N/A \* | 1.0     |
| **Mail**                                    |        |         |
| Bulkmail webservice                         | N/A    | N/A     |

\* `PostNL::getTimeframesAndNearestLocations` can be used as an alternative with more functionality: https://postnl-php.readthedocs.io/en/v1.4.x/quickstart.html#requesting-timeframes-location-and-delivery-date-at-once

## Instructions

- Clone this repo
- Run `composer install` (Don't have composer? Visit https://getcomposer.org/)
- Optionally run `composer require guzzlehttp/guzzle` to use Guzzle instead of cURL directly
- You're good to go! A small example can be found in this README. Check out the full documentation for a [quick start guide](https://postnl-php.readthedocs.io/en/v1.2.x/quickstart.html).

## Documentation

### Example

Allow a user to download a label using the default REST API

```php
<?php

use Firstred\PostNL\PostNL;
use Firstred\PostNL\Entity\Customer;
use Firstred\PostNL\Entity\Address;
use Firstred\PostNL\Entity\Shipment;
use Firstred\PostNL\Entity\Dimension;

require_once __DIR__.'/vendor/autoload.php';

$customer = Customer::create([
    'CollectionLocation' => '123456',
    'CustomerCode'       => 'DEVC',
    'CustomerNumber'     => '11223344',
    'ContactPerson'      => 'Peter',
    'Address'            => Address::create([
        'AddressType' => '02',
        'City'        => 'Hoofddorp',
        'CompanyName' => 'PostNL',
        'Countrycode' => 'NL',
        'HouseNr'     => '42',
        'Street'      => 'Siriusdreef',
        'Zipcode'     => '2132WT',
    ]),
    'Email'              => 'info@voorbeeld.nl',
    'Name'               => 'Michael',
]);

$apikey = 'YOUR_API_KEY_HERE';
$sandbox = false;

$postnl = new PostNL($customer, $apikey, $sandbox);

$barcode = $postnl->generateBarcodeByCountryCode('NL');

$shipment = Shipment::create([
    'Addresses'           => [
        Address::create([
            'AddressType' => '01',
            'City'        => 'Utrecht',
            'Countrycode' => 'NL',
            'FirstName'   => 'Peter',
            'HouseNr'     => '9',
            'HouseNrExt'  => 'a bis',
            'Name'        => 'de Ruijter',
            'Street'      => 'Bilderdijkstraat',
            'Zipcode'     => '3521VA',
        ]),
    ],
    'Barcode'             => $barcode,
    'Dimension'           => new Dimension(/* weight */ '2000'),
    'ProductCodeDelivery' => '3085',
]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="label.pdf"');
echo base64_decode($postnl->generateLabel(
    /* The actual shipment */ $shipment, 
    /* The output format */ 'GraphicFile|PDF',
    /* Immediately confirm the shipment */ true
)
    ->getResponseShipments()[0]
    ->getLabels()[0]
    ->getContent()
);
exit;
```

### Full documentation

The full documentation can be found on this page: [https://postnl-php.readthedocs.io/](https://postnl-php.readthedocs.io/)

#### Building the documentation

Generating the documentation consists of two steps:
1. Generating the reference RST files of all PHP classes available in this library.
2. Generating the HTML output which can be viewed with a browser.

#### Generating the PHP classes reference

Generating the references depends on a pretty old library. Removing other dependencies first from `require-dev` may be the best workaround to install `abbadon1334/phpdoc-to-rst`.

```
  "require-dev": {
  },
```

Then install the PHPdoc to RST tool.
```bash
composer require abbadon1334/phpdoc-to-rst -W
```

This repository includes a simple PHP file which utilizes the above-mentioned tool to generate the reference RST files programmatically. It also moves around a few files to integrate the reference with the rest of the documentation.  
Simply run (tested w/ PHP 8.2):

```
php ./build-docs-reference.php
```

##### Generating the final HTML output

The documentation is automatically built and hosted on readthedocs.io. You can build a local HTML copy by [installing Sphinx](https://www.sphinx-doc.org/en/master/usage/installation.html) and running
```bash
pip install -r docs/requirements.txt
```
to install the requirements, followed by
```
sphinx-build -b html docs builddir
```
to build the HTML output in the directory `builddir`.

## License

This library has been licensed with the MIT license.
<details>
  <summary>Full license text</summary>

The MIT License (MIT).
Copyright (c) 2017-2023 Michael Dekker (https://github.com/firstred)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

</details>
