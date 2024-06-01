<?php

namespace App\Api;

use App\Api\ConfigurableApi\ConfigurableApiConfig;
use Newms87\Danx\Api\Api;

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

    public function listRecords($page = 1, $perPage = null): array
    {
        $perPage = $perPage ?: $this->config->getPerPage();
        $params  = [
            $this->config->getPerPageField() => $perPage,
        ];

        if ($this->config->useOffset()) {
            $params[$this->config->getOffsetField()] = ($page - 1) * $perPage;
        } else {
            $params[$this->config->getPageField()] = $page;
        }

        if ($this->config->isGet()) {
            $response = $this->get('', $params)->json();
        } else {
            $response = $this->call($this->config->getMethod(), '', $params)->json();
        }

        return $response;
    }
}
