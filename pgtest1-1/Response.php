<?php


class Response
{
    private $apiPath;
    private $httpCode = 0;
    private $headers = [];
    private $body = [];
    private $xHeaders = [];
    
    public function setApiPath($apiPath)
    {
        $this->apiPath = $apiPath;
    }

    public function getApiPath()
    {
        return $this->apiPath;
    }

    public function setBody($body)
    {
        $this->body = $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setHttpCode($httpCode)
    {
        $this->httpCode = $httpCode;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function setHeaders(array $headers)
    {
        foreach ($headers as $key => $value) {
            if (substr($key, 0, 1) == 'x') {
                $this->xHeaders[$key] = $value;
            }
        }
        $this->headers = $headers;
    }

    public function getsHeaders()
    {
        return $this->headers;
    }

    public function setXHeaders(array $xHeaders = [])
    {
        $this->xHeaders = $xHeaders;
    }

    public function getXHeaders()
    {
        return $this->xHeaders;
    }
}