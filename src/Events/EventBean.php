<?php

namespace PhilKra\Events;

use Ramsey\Uuid\Uuid;

/**
 *
 * EventBean for occuring events such as Excpetions or Transactions
 *
 */
class EventBean
{
    /**
     * UUID
     *
     * @var string
     */
    private $id;

    /**
     * Error occurred on Timestamp
     *
     * @var string
     */
    private $timestamp;

    /**
     * Event Metadata
     *
     * @var array
     */
    private $meta = [
        'result' => 200,
        'type'   => 'generic'
    ];

    /**
     * Extended Contexts such as Custom and/or User
     *
     * @var array
     */
    private $contexts = [
        'user'     => [],
        'custom'   => [],
        'tags'     => [],
        'response' => [
            'finished'     => true,
            'headers_sent' => true,
            'status_code'  => 200,
        ],
    ];

    /**
     * Init the Event with the Timestamp and UUID
     *
     * @link https://github.com/philkra/elastic-apm-php-agent/issues/3
     *
     * @param array $contexts
     */
    public function __construct(array $contexts)
    {
        // Generate Random UUID
        $this->id = Uuid::uuid4()->toString();

        // Merge Initial Context
        $this->contexts = array_merge($this->contexts, $contexts);

        // Get UTC timestamp of Now
        $timestamp = \DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)));
        $timestamp->setTimeZone(new \DateTimeZone('UTC'));
        $this->timestamp = $timestamp->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Get the Event Id
     *
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * Get the Event's Timestamp
     *
     * @return string
     */
    public function getTimestamp() : string
    {
        return $this->timestamp;
    }

    /**
     * Set the Transaction Meta data
     *
     * @param array $meta
     *
     * @return void
     */
    final public function setMeta(array $meta)
    {
        $this->meta = array_merge($this->meta, $meta);
    }

    /**
     * Set Meta data of User Context
     *
     * @param array $userContext
     */
    final public function setUserContext(array $userContext)
    {
        $this->contexts['user'] = array_merge($this->contexts['user'], $userContext);
    }

    /**
     * Set custom Meta data for the Transaction in Context
     *
     * @param array $customContext
     */
    final public function setCustomContext(array $customContext)
    {
        $this->contexts['custom'] = array_merge($this->contexts['custom'], $customContext);
    }

    /**
     * Set Transaction Response
     *
     * @param array $response
     */
    final public function setResponse(array $response)
    {
        $this->contexts['response'] = array_merge($this->contexts['response'], $response);
    }

    /**
     * Set Tags for this Transaction
     *
     * @param array $tags
     */
    final public function setTags(array $tags)
    {
        $this->contexts['tags'] = array_merge($this->contexts['tags'], $tags);
    }

    /**
     * Get Type defined in Meta
     *
     * @return string
     */
    final protected function getMetaType() : string
    {
        return $this->meta['type'];
    }

    /**
     * Get the Result of the Event from the Meta store
     *
     * @return string
     */
    final protected function getMetaResult() : string
    {
        return (string)$this->meta['result'];
    }

    /**
     * Get the Events Context
     *
     * @link https://www.elastic.co/guide/en/apm/server/current/transaction-api.html#transaction-context-schema
     *
     * @return array
     */
    final protected function getContext() : array
    {
        $headers = getallheaders();
        $http_or_https = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        
        // Build Context Stub
        $SERVER_PROTOCOL = $_SERVER['SERVER_PROTOCOL'] ?? '';
        $context         = [
            'request' => [
                'http_version' => substr($SERVER_PROTOCOL, strpos($SERVER_PROTOCOL, '/')),
                'method'       => $_SERVER['REQUEST_METHOD'] ?? 'cli',
                'socket'       => [
                    'remote_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                    'encrypted'      => isset($_SERVER['HTTPS'])
                ],
                'response' => $this->contexts['response'],
                'url'          => [
                    'protocol' => $http_or_https,
                    'hostname' => $_SERVER['SERVER_NAME'] ?? '',
                    'port'     => $_SERVER['SERVER_PORT'] ?? '',
                    'pathname' => $_SERVER['SCRIPT_NAME'] ?? '',
                    'search'   => '?' . (($_SERVER['QUERY_STRING'] ?? '') ?? ''),
                    'full' => $http_or_https . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']
                ],
                'headers' => [
                    'user-agent' => $headers['User-Agent'] ?? '',
                    'cookie'     => $headers['Cookie'] ?? ''
                ],
                'env' => $_SERVER,
            ]
        ];

        // Add Cookies Map
        if (empty($_COOKIE) === false) {
            $context['request']['cookies'] = $_COOKIE;
        }

        // Add User Context
        if (empty($this->contexts['user']) === false) {
            $context['request']['user'] = $this->contexts['user'];
        }

        // Add Custom Context
        if (empty($this->contexts['custom']) === false) {
            $context['request']['custom'] = $this->contexts['custom'];
        }

        // Add Tags Context
        if (empty($this->contexts['tags']) === false) {
            $context['request']['tags'] = $this->contexts['tags'];
        }

        return $context;
    }
}
