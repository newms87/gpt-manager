<?php

namespace App\Api\ConfigurableApi;

class ConfigurableApiConfig
{
    protected array $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getMethod()
    {
        return $this->config['method'] ?? 'GET';
    }

    public function isGet()
    {
        return strtoupper($this->getMethod()) === 'GET';
    }
    
    public function getPerPage()
    {
        return $this->config['per_page'] ?? 1000;
    }

    public function useOffset()
    {
        return $this->config['use_offset'] ?? false;
    }

    public function getRateLimits()
    {
        return $this->config['rate_limits'] ?? [];
    }

    public function getHeaders()
    {
        return $this->config['headers'] ?? [];
    }

    public function getTotalField()
    {
        return $this->config['fields']['total'] ?? 'total';
    }

    public function getItemsField()
    {
        return $this->config['fields']['items'] ?? 'items';
    }

    public function getPerPageField()
    {
        return $this->config['fields']['per_page'] ?? 'per_page';
    }

    public function getPageField()
    {
        return $this->config['fields']['page'] ?? 'page';
    }

    public function getOffsetField()
    {
        return $this->config['fields']['offset'] ?? 'offset';
    }

    public function getTimestampField()
    {
        return $this->config['fields']['timestamp'] ?? 'timestamp';
    }
}
