TiargGeneratorBundle
====================
**WARNING! Bundle now in development, not for use in production environment!**

[![Latest Stable Version](https://poser.pugx.org/tiarg/generator-bundle/v/stable)](https://packagist.org/packages/tiarg/generator-bundle)
[![Total Downloads](https://poser.pugx.org/tiarg/generator-bundle/downloads)](https://packagist.org/packages/tiarg/generator-bundle)
[![License](https://poser.pugx.org/tiarg/generator-bundle/license)](https://packagist.org/packages/tiarg/generator-bundle)

The **TiargGeneratorBundle** bundle allows you to generate JSON CRUD APIs for your doctrine entities, with json schemas derived from Doctrine Metadata, Annotations and the Symfony Validator component.

Documentation
-------------

For documentation, see:

    Resources/doc/

[Read the documentation](https://github.com/Lube/tiarg_generator/blob/master/Resources/doc/index.rst)

Installation
------------

Install through composer: 

*First step: require bundle*
```
composer require tiarg/generator-bundle
```

*Second step: enable bundle*
```php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Tiarg\GeneratorBundle\TiargGeneratorBundle(),
    );
}
```
Usage
------------

```bash
$ app/console api:generate:json
$ app/console api:generate
```

Contributing
------------

See
[CONTRIBUTING](https://github.com/Lube/tiarg_generator/blob/master/CONTRIBUTING.md)
file.


Credits
-------

The design is heavily inspired by the Doctrine CRUD Generator.

This bundle relies on [JMSSerializer](https://github.com/schmittjoh/JMSSerializerBundle), [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle), [JsonSchemaBundle](https://github.com/HadesArchitect/JsonSchemaBundle).


License
-------

This bundle is released under the MIT license. See the complete license in the
bundle:

    Resources/meta/LICENSE