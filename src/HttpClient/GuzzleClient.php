<?php
/**
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @copyright 2017-2018 thirty bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace ThirtyBees\PostNL\HttpClient;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use ThirtyBees\PostNL\Exception\HttpClientException;

/**
 * Class GuzzleClient
 *
 * @package ThirtyBees\PostNL\HttpClient
 */
class GuzzleClient implements ClientInterface, LoggerAwareInterface
{
    const DEFAULT_TIMEOUT = 60;
    const DEFAULT_CONNECT_TIMEOUT = 20;

    /** @var static $instance */
    protected static $instance;
    /** @var array $defaultOptions */
    protected $defaultOptions = [];
    /**
     * List of pending PSR-7 requests
     *
     * @var Request[]
     */
    protected $pendingRequests = [];
    /** @var LoggerInterface $logger */
    protected $logger;
    /** @var int $timeout */
    private $timeout = self::DEFAULT_TIMEOUT;
    /** @var int $connectTimeout */
    private $connectTimeout = self::DEFAULT_CONNECT_TIMEOUT;

    /** @var int $maxRetries */
    private $maxRetries = 1;
    /** @var Client $client */
    private $client;

    /**
     * Get the Guzzle client
     *
     * @return Client
     */
    private function getClient()
    {
        if (!$this->client) {
            // Initialize Guzzle and the retry middleware, include the default options
            $stack = HandlerStack::create(\GuzzleHttp\choose_handler());
            $stack->push(\GuzzleHttp\Middleware::retry(static::createRetryHandler()));
            $guzzle = new Client(array_merge(
                $this->defaultOptions,
                [
                    'timeout'         => $this->timeout,
                    'connect_timeout' => $this->connectTimeout,
                ]
            ));

            $this->client = $guzzle;
        }

        return $this->client;
    }

    /**
     * @return GuzzleClient|static
     */
    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Set Guzzle option
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return GuzzleClient
     */
    public function setOption($name, $value) {
        // Set the default option
        $this->defaultOptions[$name] = $value;
        // Reset the non-mutable Guzzle client
        $this->client = null;

        return $this;
    }

    /**
     * Get Guzzle option
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getOption($name)
    {
        if (isset($this->defaultOptions[$name])) {
            return $this->defaultOptions[$name];
        }

        return null;
    }

    /**
     * Set the verify setting
     *
     * @param bool|string $verify
     *
     * @return $this
     */
    public function setVerify($verify)
    {
        // Set the verify option
        $this->defaultOptions['verify'] = $verify;
        // Reset the non-mutable Guzzle client
        $this->client = null;

        return $this;
    }

    /**
     * Return verify setting
     *
     * @return bool|string
     */
    public function getVerify()
    {
        if (isset($this->defaultOptions['verify'])) {
            return $this->defaultOptions['verify'];
        }

        return false;
    }

    /**
     * Set the amount of retries
     *
     * @param int $maxRetries
     *
     * @return $this
     */
    public function setMaxRetries($maxRetries)
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    /**
     * Return max retries
     *
     * @return int
     */
    public function getMaxRetries()
    {
        return $this->maxRetries;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger
     *
     * @return GuzzleClient
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Adds a request to the list of pending requests
     * Using the ID you can replace a request
     *
     * @param string $id      Request ID
     * @param string $request PSR-7 request
     *
     * @return int|string
     */
    public function addOrUpdateRequest($id, $request)
    {
        if (is_null($id)) {
            return array_push($this->pendingRequests, $request);
        }

        $this->pendingRequests[$id] = $request;

        return $id;
    }

    /**
     * Remove a request from the list of pending requests
     *
     * @param string $id
     */
    public function removeRequest($id)
    {
        unset($this->pendingRequests[$id]);
    }

    /**
     * Clear all pending requests
     */
    public function clearRequests()
    {
        $this->pendingRequests = [];
    }

    /**
     * Do a single request
     *
     * Exceptions are captured into the result array
     *
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception|HttpClientException
     */
    public function doRequest(Request $request)
    {
        // Initialize Guzzle, include the default options
        $guzzle = $this->getClient();
        try {
            $response = $guzzle->send($request);
        } catch (GuzzleException $e) {
            throw new HttpClientException($e->getMessage(), $e->getCode(), $e);
        }
        if ($response instanceof Response && $this->logger instanceof LoggerInterface) {
            $this->logger->debug(\GuzzleHttp\Psr7\str($response));
        }

        return $response;
    }

    /**
     * Do all async requests
     *
     * Exceptions are captured into the result array
     *
     * @param Request[] $requests
     *
     * @return Response|Response[]|HttpClientException|HttpClientException[]
     */
    public function doRequests($requests = [])
    {
        // If this is a single request, create the requests array
        if (!is_array($requests)) {
            if (!$requests instanceof Request) {
                return [];
            }

            $requests = [$requests];
        }

        // Handle pending requests
        $requests = $this->pendingRequests + $requests;
        $this->clearRequests();

        $guzzle = $this->getClient();
        // Concurrent requests
        $promises = [];
        foreach ($requests as $index => $request) {
            if ($request instanceof Request && $this->logger instanceof LoggerInterface) {
                $this->logger->debug(\GuzzleHttp\Psr7\str($request));
            }
            $promises[$index] = $guzzle->sendAsync($request);
        }

        $responses = \GuzzleHttp\Promise\settle($promises)->wait();
        foreach ($responses as &$response) {
            if (!empty($response['value'])) {
                $response = $response['value'];
            } elseif (!empty($response['reason'])) {
                if ($response['reason'] instanceof GuzzleException) {
                    if (method_exists($response['reason'], 'getMessage')
                        && method_exists($response['reason'], 'getCode')
                    ) {
                        $response = new HttpClientException(
                            $response['reason']->getMessage(),
                            $response['reason']->getCode(),
                            $response['reason']
                        );
                    } else {
                        $response = new HttpClientException(null, null, $response['reason']);
                    }
                 } else {
                    $response = $response['reason'];
                }
            } else {
                $response = \ThirtyBees\PostNL\Exception\ResponseException('Unknown reponse type');
            }
            if ($response instanceof Response && $this->logger instanceof LoggerInterface) {
                $this->logger->debug(\GuzzleHttp\Psr7\str($response));
            }
        }

        return $responses;
    }

    /**
     * Create the retry handler
     *
     * @return \Closure
     */
    protected function createRetryHandler()
    {
        return function (
            $retries,
            Request $request,
            Response $response = null,
            RequestException $exception = null
        ) {
            if ($retries >= $this->maxRetries) {
                return false;
            }
            if (!(static::isServerError($response) || static::isConnectError($exception))) {
                return false;
            }

            return true;
        };
    }

    /**
     * @param Response $response
     *
     * @return bool
     */
    protected function isServerError(Response $response = null)
    {
        return $response && $response->getStatusCode() >= 500;
    }

    /**
     * @param RequestException $exception
     *
     * @return bool
     */
    protected function isConnectError(RequestException $exception = null)
    {
        return $exception instanceof ConnectException;
    }
}
