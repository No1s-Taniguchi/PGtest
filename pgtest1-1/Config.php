<?php

class Config
{
    protected $timeout = 5;
    protected $connectionTimeout = 5;
    protected $maxRetries = 0;
    protected $retriesDelay = 1;
    protected $decodeJsonAsArray = false;
    protected $userAgent = 'TwitterOAuth (+https://twitteroauth.com)';
    protected $proxy = [];
    protected $gzipEncoding = true;
    protected $chunkSize = 250000; // 0.25 MegaByte

    public function setTimeouts($connectionTimeout, $timeout)
    {
        $this->connectionTimeout = (int)$connectionTimeout;
        $this->timeout = (int)$timeout;
    }

    public function setRetries($maxRetries, $retriesDelay)
    {
        $this->maxRetries = (int)$maxRetries;
        $this->retriesDelay = (int)$retriesDelay;
    }

    public function setDecodeJsonAsArray($value)
    {
        $this->decodeJsonAsArray = (bool)$value;
    }

    public function setUserAgent($userAgent)
    {
        $this->userAgent = (string)$userAgent;
    }

    public function setProxy(array $proxy)
    {
        $this->proxy = $proxy;
    }

    public function setGzipEncoding($gzipEncoding)
    {
        $this->gzipEncoding = (bool)$gzipEncoding;
    }

    public function setChunkSize($value)
    {
        $this->chunkSize = (int)$value;
    }
}