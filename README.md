# Magento 2 Elasticsearch Logger

> A Magento 2 module to send logs directly to Elasticsearch

## Installation

1. Install composer package  
```sh
composer require kingfisherdirect/magento2-elasticsearch-logger
```
2. Then run  
```sh
bin/magento setup:upgrade
```
3. Configure module through `bin/magento setup:install` or `env.php` file  
```sh
bin/magento setup:install \
    --elasticsearch-logger-config='{"hosts": ["http://elasticsearch:9200"]}'\
    --elasticsearch-logger-index='magento2-logs'
```
4. Enable Handler in `app/etc/di.xml`  
```xml
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <!-- some XML -->

    <!-- Find configuration for class Magento\Framework\Logger\Monolog -->
    <type name="Magento\Framework\Logger\Monolog">
        <arguments>
            <argument name="name" xsi:type="string">main</argument>
            <argument name="handlers"  xsi:type="array">
                <item name="system" xsi:type="object">Magento\Framework\Logger\Handler\System</item>
                <item name="debug" xsi:type="object">Magento\Framework\Logger\Handler\Debug</item>
                <item name="syslog" xsi:type="object">Magento\Framework\Logger\Handler\Syslog</item>
                <!-- Add following line -->
                <item name="elasticsearch" xsi:type="object">KingfisherDirect\ElasticSearchLogger\Handler\ElasticSearchHandler</item>
            </argument>
        </arguments>
    </type>

    <!-- some other XML -->
</config>
```

## Configuration

Module is configurable through `app/etc/env.php` file as Elasticsearch details
may be different per deployment.

To set the values it's best to use setup:install script as described in installation

```sh
bin/magento setup:install \
    --elasticsearch-logger-config='{"hosts": ["http://elasticsearch:9200"]}' \
    --elasticsearch-logger-index='magento2-logs'
```

#### elasticsearch-logger-config

This is JSON configuration that is then deserialized and used as a configuration
to build Elasticsearch Client. Basic setup would be as follows:

```json
{
    "hosts": [
        "http://elasticsearch.example.org:9200"
    ]
}
```

In case you use elasti.co service you'll then you should use this config:

```json
{
    "elasticCloudId": "ID",
    "basicAuthentication": ["USER", "PASSWORD"]
}
```

Internally it uses `Elasticsearch\ClientBuilder::fromConfig()` method for configuration, so you may want to dig into code or check library on https://github.com/elastic/elasticsearch-php

If this value is empty, no logs will be sent to Elasticsearch.

In `env.php` this value is stored as PHP array, not string containing JSON

#### elasticsearch-logger-index

_Default: `monolog`_

Name of the index where logs should go

## Tests

Not yet any.
