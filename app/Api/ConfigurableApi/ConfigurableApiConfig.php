<?php

namespace App\Api\ConfigurableApi;

use Carbon\Carbon;

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

    public function getListUri()
    {
        return $this->config['list_uri'] ?? '';
    }

    public function getTimestampFormat(): string
    {
        return $this->config['timestamp_format'] ?? 'Y-m-d H:i:s';
    }

    public function getMinimumTimestamp(): Carbon
    {
        return carbon($this->config['minimum_timestamp'] ?? null);
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

    public function getItemIdField()
    {
        return $this->config['fields']['id'] ?? 'id';
    }

    public function getItemDateField()
    {
        return $this->config['fields']['date'] ?? 'date';
    }

    public function getItemNameField()
    {
        return $this->config['fields']['name'] ?? 'name';
    }
}
