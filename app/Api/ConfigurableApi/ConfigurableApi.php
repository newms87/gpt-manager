<?php

namespace App\Api\ConfigurableApi;

use GuzzleHttp\Exception\GuzzleException;
use Newms87\Danx\Api\Api;
use Newms87\Danx\Exceptions\ApiException;
use Newms87\Danx\Exceptions\ApiRequestException;

class ConfigurableApi extends Api
{
    public static string $serviceName = 'Configurable API';

    protected ConfigurableApiConfig $config;

    public function __construct($serviceName, $url, ConfigurableApiConfig $config)
    {
        static::$serviceName = $serviceName;
        $this->config        = $config;
        $this->baseApiUrl    = $url;
        $this->rateLimits    = $config->getRateLimits();
    }

    public function getRequestHeaders(): array
    {
        return $this->config->getHeaders() + parent::getRequestHeaders();
    }

    /**
     * Fetches a list of items from the API
     *
     * @throws ApiException
     * @throws ApiRequestException
     * @throws GuzzleException
     */
    public function getItems(string $uri, array $params = [], int $page = 1, ?int $perPage = null): ConfigurableApiListResponse
    {
        $perPage = $perPage ?: $this->config->getPerPage();

        $params[$this->config->getPerPageField()] = $perPage;

        if ($this->config->useOffset()) {
            $params[$this->config->getOffsetField()] = ($page - 1) * $perPage;
        } else {
            $params[$this->config->getPageField()] = $page;
        }

        if ($this->config->isGet()) {
            $response = $this->get($uri, $params)->json();
        } else {
            $response = $this->call($this->config->getMethod(), $uri, $params)->json();
        }

        if (!$response) {
            throw new ApiException('The response from the API was empty');
        }

        return new ConfigurableApiListResponse($this->config, $response, $page);
    }
}
