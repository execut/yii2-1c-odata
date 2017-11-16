# Yii2 wrapper via activeRecord for 1C oData
## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

### Install

Either run

```
$ php composer.phar require execut/yii2-1c-odata "dev-master"
```

or add

```
"execut/yii2-1c-odata": "dev-master"
```

to the ```require``` section of your `composer.json` file.

Temporarily until they accept the request https://github.com/kilylabs/odata-1c/pull/1 set my fork in you root composer.json:
```
  "require": {
    ...
    "kilylabs/odata-1c": "dev-execut-extending",
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/execut/odata-1c.git"
    }
  ]
```

## Configuration example
Add to application config folowing rules:
```php
[
    'components' => [
        'oData' => [
            'class' => \execut\oData\Client::class,
            'host' => $odataHost,
            'path' => $odataPath,
            'options' => [
                'auth' => [
                    $odataLogin,
                    $odataPassword,
                ],
            ],
            'customColumnsTypes' => [
                // Here you custom columns types stubs configuration. Example:
                'Catalog_Контрагенты' => [
                    'НаименованиеПолное' => 'text',
                ],
            ],
        ],
    ],
];
```

After configuration, you must declare your models and queries on the basis of two classes:
execut\oData\ActiveRecord and execut\oData\ActiveQuery

## Your help was, would be useful
For more information, there is not enough time =(

## Planned
* Unit tests cover
* Extending functional to standard oData, without 1C

## License

**yii2-1c-odata** is released under the Apache License Version 2.0. See the bundled `LICENSE.md` for details.
