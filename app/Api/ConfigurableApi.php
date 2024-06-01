<?php

namespace App\Api;

use App\Api\ConfigurableApi\ConfigurableApiConfig;
use Newms87\Danx\Api\Api;
use Newms87\Danx\Exceptions\ApiException;

class ConfigurableApi extends Api
{
    public static string $serviceName = 'Configurable API';

    protected ConfigurableApiConfig $config;

    public function __construct($serviceName, $url, ConfigurableApiConfig $config)
    {
        static::$serviceName = $serviceName;
        $this->config        = $config;
        $this->baseApiUrl    = $url;
    }

    public function getRequestHeaders(): array
    {
        return $this->config->getHeaders() + parent::getRequestHeaders();
    }

    public function getItems($uri, $params = [], $page = 1, $perPage = null): array
    {
        $perPage = $perPage ?: $this->config->getPerPage();

        $params [$this->config->getPerPageField()] = $perPage;

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
            throw new ApiException("The response from the API was empty");
        }

        $itemsField = $this->config->getItemsField();

        if (!isset($response[$itemsField])) {
            throw new ApiException("The response from the API did not contain the expected items field: " . $itemsField);
        }

        return $response[$itemsField] ?: [];
    }
}
