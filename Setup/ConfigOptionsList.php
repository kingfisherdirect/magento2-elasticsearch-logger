<?php

namespace KingfisherDirect\ElasticSearchLogger\Setup;

use Elasticsearch\ClientBuilder;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\Data\ConfigData;
use Magento\Framework\Config\File\ConfigFilePool;
use Magento\Framework\Setup\ConfigOptionsListInterface;
use Magento\Framework\Setup\Option\TextConfigOption;

class ConfigOptionsList implements ConfigOptionsListInterface
{
    const INPUT_KEY_ELASTICSEARCH_LOGGER_CONFIG = 'elasticsearch-logger-config';
    const INPUT_KEY_ELASTICSEARCH_LOGGER_INDEX = 'elasticsearch-logger-index';

    const CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG = 'elasticsearch_logger/config';
    const CONFIG_PATH_ELASTICSEARCH_LOGGER_INDEX = 'elasticsearch_logger/index';

    const DEFAULT_INDEX = 'magento2_log';

    /**
     * Gets a list of input options so that user can provide required
     * information that will be used in deployment config file
     *
     * @return Option\AbstractConfigOption[]
     */
    public function getOptions()
    {
        return [
            new TextConfigOption(
                self::INPUT_KEY_ELASTICSEARCH_LOGGER_CONFIG,
                TextConfigOption::FRONTEND_WIZARD_TEXTAREA,
                self::CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG,
                'Elasticsearch JSON configuration to access ES cluster for logging purposes'
            ),
            new TextConfigOption(
                self::INPUT_KEY_ELASTICSEARCH_LOGGER_INDEX,
                TextConfigOption::FRONTEND_WIZARD_TEXT,
                self::CONFIG_PATH_ELASTICSEARCH_LOGGER_INDEX,
                'Elasticsearch index name for logging (Default: magento2_logs)',
                self::DEFAULT_INDEX
            )
        ];
    }

    /**
     * Creates array of ConfigData objects from user input data.
     * Data in these objects will be stored in array form in deployment config file.
     *
     * @param array $options
     * @param DeploymentConfig $deploymentConfig
     * @return \Magento\Framework\Config\Data\ConfigData[]
     */
    public function createConfig(array $options, DeploymentConfig $deploymentConfig)
    {
        $configData = new ConfigData(ConfigFilePool::APP_ENV);

        $jsonConfig = $options[self::INPUT_KEY_ELASTICSEARCH_LOGGER_CONFIG] ?: $deploymentConfig->get(self::CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG);
        $configData->set(self::CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG, $jsonConfig ? $this->decodeJson($jsonConfig) : null);

        if ($options[self::INPUT_KEY_ELASTICSEARCH_LOGGER_INDEX]) {
            $configData->set(self::CONFIG_PATH_ELASTICSEARCH_LOGGER_INDEX, $options[self::INPUT_KEY_ELASTICSEARCH_LOGGER_INDEX]);
        }

        return [$configData];
    }

    /**
     * Validates user input option values and returns error messages
     *
     * @param array $options
     * @param DeploymentConfig $deploymentConfig
     * @return string[]
     */
    public function validate(array $options, DeploymentConfig $deploymentConfig)
    {
        $errors = [];

        $jsonConfig = $options[self::INPUT_KEY_ELASTICSEARCH_LOGGER_CONFIG] ?: $deploymentConfig->get(self::CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG);

        if ($jsonConfig) {
            try {
                $config = $this->decodeJson($jsonConfig);
            } catch (\JsonException $exception) {
                $errors[] = 'Failed decoding ElasticSearch config JSON: '.$exception->getMessage();
            }
        }

        $esClient = ClientBuilder::fromConfig($config);
        $esClient->info();

        $index = $options[self::INPUT_KEY_ELASTICSEARCH_LOGGER_INDEX] ?: $deploymentConfig->get(self::CONFIG_PATH_ELASTICSEARCH_LOGGER_INDEX);

        if ($index && !preg_match('/^[a-zA-Z0-9._]+$/', $index)) {
            $errors[] = 'ElasticSearch index name must only contain letters and numbers';
        }

        return $errors;
    }

    private function decodeJson(string $config): array
    {
        return json_decode($config, true, 16, JSON_THROW_ON_ERROR);
    }
}
