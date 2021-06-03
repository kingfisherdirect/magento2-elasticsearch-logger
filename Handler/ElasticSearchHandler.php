<?php

namespace KingfisherDirect\ElasticSearchLogger\Handler;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\RuntimeException as ElasticsearchRuntimeException;
use InvalidArgumentException;
use KingfisherDirect\ElasticSearchLogger\Formatter\ElasticsearchFormatter;
use KingfisherDirect\ElasticSearchLogger\Setup\ConfigOptionsList;
use Magento\Framework\App\DeploymentConfig;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use RuntimeException;
use Throwable;

class ElasticSearchHandler extends AbstractProcessingHandler
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array Handler config options
     */
    protected $options = [];

    /**
     * @param Client     $client  Elasticsearch Client object
     * @param array      $options Handler configuration
     * @param string|int $level   The minimum logging level at which this handler will be triggered
     * @param bool       $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(DeploymentConfig $deploymentConfig, $level = Logger::INFO, bool $bubble = true)
    {
        $config = $deploymentConfig->get(ConfigOptionsList::CONFIG_PATH_ELASTICSEARCH_LOGGER_CONFIG);

        if (is_array($config) && !empty($config)) {
            $index = $deploymentConfig->get(ConfigOptionsList::CONFIG_PATH_ELASTICSEARCH_LOGGER_INDEX, 'monolog');
            $this->options = [
                'index'        => $index,  // Elastic index name
                'type'         => '_doc',  // Elastic document type
                'ignore_error' => false,   // Suppress Elasticsearch exceptions
            ];

            try {
                $this->client = ClientBuilder::fromConfig($config, true);
            } catch (\Exception $e) {
            }
        }

        parent::__construct($level, $bubble);
    }

    public function isHandling(array $record)
    {
        return $this->client && parent::isHandling($record);
    }

    /**
     * {@inheritDoc}
     */
    protected function write(array $record)
    {
        $this->bulkSend([$record['formatted']]);
    }

    /**
     * {@inheritdoc}
     */
    public function setFormatter(FormatterInterface $formatter)
    {
        if ($formatter instanceof ElasticsearchFormatter) {
            return parent::setFormatter($formatter);
        }

        throw new InvalidArgumentException('ElasticsearchHandler is only compatible with ElasticsearchFormatter');
    }

    /**
     * Getter options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new ElasticsearchFormatter($this->options['index'], $this->options['type']);
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records)
    {
        $documents = $this->getFormatter()->formatBatch($records);
        $this->bulkSend($documents);
    }

    /**
     * Use Elasticsearch bulk API to send list of documents
     *
     * @param  array             $records
     * @throws \RuntimeException
     */
    protected function bulkSend(array $records)
    {
        try {
            $params = [
                'body' => [],
            ];

            foreach ($records as $record) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $record['_index'],
                        '_type'  => $record['_type'],
                    ],
                ];
                unset($record['_index'], $record['_type']);

                $params['body'][] = $record;
            }

            $responses = $this->client->bulk($params);

            if ($responses['errors'] === true) {
                throw $this->createExceptionFromResponses($responses);
            }
        } catch (Throwable $e) {
            if (! $this->options['ignore_error']) {
                throw new RuntimeException('Error sending messages to Elasticsearch', 0, $e);
            }
        }
    }

    /**
     * Creates elasticsearch exception from responses array
     *
     * Only the first error is converted into an exception.
     *
     * @param array $responses returned by $this->client->bulk()
     */
    protected function createExceptionFromResponses(array $responses)
    {
        foreach ($responses['items'] ?? [] as $item) {
            if (isset($item['index']['error'])) {
                return $this->createExceptionFromError($item['index']['error']);
            }
        }

        return new ElasticsearchRuntimeException('Elasticsearch failed to index one or more records.');
    }

    /**
     * Creates elasticsearch exception from error array
     *
     * @param array $error
     */
    protected function createExceptionFromError(array $error)
    {
        $previous = isset($error['caused_by']) ? $this->createExceptionFromError($error['caused_by']) : null;

        return new ElasticsearchRuntimeException($error['type'] . ': ' . $error['reason'], 0, $previous);
    }
}
