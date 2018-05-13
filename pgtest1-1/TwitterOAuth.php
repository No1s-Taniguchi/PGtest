<?php

require_once dirname(__FILE__).'/Config.php';
require_once dirname(__FILE__).'/Response.php';
require_once dirname(__FILE__).'/HmacSha1.php';
require_once dirname(__FILE__).'/Consumer.php';
require_once dirname(__FILE__).'/Token.php';
require_once dirname(__FILE__).'/Request.php';
require_once dirname(__FILE__).'/Util.php';
require_once dirname(__FILE__).'/TwitterOAuthException.php';
require_once dirname(__FILE__).'/JsonDecoder.php';

class TwitterOAuth extends Config
{
    const API_VERSION = '1.1';
    const API_HOST = 'https://api.twitter.com';
    const UPLOAD_HOST = 'https://upload.twitter.com';
    private $response;
    private $bearer;
    private $consumer;
    private $token;
    private $signatureMethod;
    private $attempts = 0;

    public function __construct($consumerKey, $consumerSecret, $oauthToken = null, $oauthTokenSecret = null)
    {
        $this->resetLastResponse();
        $this->signatureMethod = new HmacSha1();
        $this->consumer = new Consumer($consumerKey, $consumerSecret);
        if (!empty($oauthToken) && !empty($oauthTokenSecret)) {
            $this->setOauthToken($oauthToken, $oauthTokenSecret);
        }
        if (empty($oauthToken) && !empty($oauthTokenSecret)) {
            $this->setBearer($oauthTokenSecret);
        }
    }

    public function setOauthToken($oauthToken, $oauthTokenSecret)
    {
        $this->token = new Token($oauthToken, $oauthTokenSecret);
        $this->bearer = null;
    }

    public function setBearer($oauthTokenSecret)
    {
        $this->bearer = $oauthTokenSecret;
        $this->token = null;
    }

    public function getLastApiPath()
    {
        return $this->response->getApiPath();
    }

    public function getLastHttpCode()
    {
        return $this->response->getHttpCode();
    }

    public function getLastXHeaders()
    {
        return $this->response->getXHeaders();
    }

    public function getLastBody()
    {
        return $this->response->getBody();
    }

    public function resetLastResponse()
    {
        $this->response = new Response();
    }

    private function resetAttemptsNumber()
    {
        $this->attempts = 0;
    }

    private function sleepIfNeeded()
    {
        if ($this->maxRetries && $this->attempts) {
            sleep($this->retriesDelay);
        }
    }

    public function url($path, array $parameters)
    {
        $this->resetLastResponse();
        $this->response->setApiPath($path);
        $query = http_build_query($parameters);
        return sprintf('%s/%s?%s', self::API_HOST, $path, $query);
    }

    public function oauth($path, array $parameters = [])
    {
        $response = [];
        $this->resetLastResponse();
        $this->response->setApiPath($path);
        $url = sprintf('%s/%s', self::API_HOST, $path);
        $result = $this->oAuthRequest($url, 'POST', $parameters);
        if ($this->getLastHttpCode() != 200) {
            throw new TwitterOAuthException($result);
        }
        parse_str($result, $response);
        $this->response->setBody($response);
        return $response;
    }

    public function oauth2($path, array $parameters = [])
    {
        $method = 'POST';
        $this->resetLastResponse();
        $this->response->setApiPath($path);
        $url = sprintf('%s/%s', self::API_HOST, $path);
        $request = Request::fromConsumerAndToken($this->consumer, $this->token, $method, $url, $parameters);
        $authorization = 'Authorization: Basic ' . $this->encodeAppAuthorization($this->consumer);
        $result = $this->request($request->getNormalizedHttpUrl(), $method, $authorization, $parameters);
        $response = JsonDecoder::decode($result, $this->decodeJsonAsArray);
        $this->response->setBody($response);
        return $response;
    }

    public function get($path, array $parameters = [])
    {
        return $this->http('GET', self::API_HOST, $path, $parameters);
    }

    public function post($path, array $parameters = [])
    {
        return $this->http('POST', self::API_HOST, $path, $parameters);
    }

    public function delete($path, array $parameters = [])
    {
        return $this->http('DELETE', self::API_HOST, $path, $parameters);
    }

    public function put($path, array $parameters = [])
    {
        return $this->http('PUT', self::API_HOST, $path, $parameters);
    }

    public function upload($path, array $parameters = [], $chunked = false)
    {
        if ($chunked) {
            return $this->uploadMediaChunked($path, $parameters);
        } else {
            return $this->uploadMediaNotChunked($path, $parameters);
        }
    }

    private function uploadMediaNotChunked($path, array $parameters)
    {
        if (! is_readable($parameters['media']) ||
            ($file = file_get_contents($parameters['media'])) === false) {
            throw new \InvalidArgumentException('You must supply a readable file');
        }
        $parameters['media'] = base64_encode($file);
        return $this->http('POST', self::UPLOAD_HOST, $path, $parameters);
    }

    private function uploadMediaChunked($path, array $parameters)
    {
        $init = $this->http('POST', self::UPLOAD_HOST, $path, $this->mediaInitParameters($parameters));
        // Append
        $segmentIndex = 0;
        $media = fopen($parameters['media'], 'rb');
        while (!feof($media)) {
            $this->http('POST', self::UPLOAD_HOST, 'media/upload', [
                'command' => 'APPEND',
                'media_id' => $init->media_id_string,
                'segment_index' => $segmentIndex++,
                'media_data' => base64_encode(fread($media, $this->chunkSize))
            ]);
        }
        fclose($media);
        // Finalize
        $finalize = $this->http('POST', self::UPLOAD_HOST, 'media/upload', [
            'command' => 'FINALIZE',
            'media_id' => $init->media_id_string
        ]);
        return $finalize;
    }

    private function mediaInitParameters(array $parameters)
    {
        $return = [
            'command' => 'INIT',
            'media_type' => $parameters['media_type'],
            'total_bytes' => filesize($parameters['media'])
        ];
        if (isset($parameters['additional_owners'])) {
            $return['additional_owners'] = $parameters['additional_owners'];
        }
        if (isset($parameters['media_category'])) {
            $return['media_category'] = $parameters['media_category'];
        }
        return $return;
    }

    private function http($method, $host, $path, array $parameters)
    {
        $this->resetLastResponse();
        $this->resetAttemptsNumber();
        $url = sprintf('%s/%s/%s.json', $host, self::API_VERSION, $path);
        $this->response->setApiPath($path);
        return $this->makeRequests($url, $method, $parameters);
    }

    private function makeRequests($url, $method, array $parameters)
    {
        do {
            $this->sleepIfNeeded();
            $result = $this->oAuthRequest($url, $method, $parameters);
            $response = JsonDecoder::decode($result, $this->decodeJsonAsArray);
            $this->response->setBody($response);
            $this->attempts++;
            // Retry up to our $maxRetries number if we get errors greater than 500 (over capacity etc)
        } while ($this->requestsAvailable());
        return $response;
    }

    private function requestsAvailable()
    {
        return ($this->maxRetries && ($this->attempts <= $this->maxRetries) && $this->getLastHttpCode() >= 500);
    }

    private function oAuthRequest($url, $method, array $parameters)
    {
        $request = Request::fromConsumerAndToken($this->consumer, $this->token, $method, $url, $parameters);
        if (array_key_exists('oauth_callback', $parameters)) {
            // Twitter doesn't like oauth_callback as a parameter.
            unset($parameters['oauth_callback']);
        }
        if ($this->bearer === null) {
            $request->signRequest($this->signatureMethod, $this->consumer, $this->token);
            $authorization = $request->toHeader();
            if (array_key_exists('oauth_verifier', $parameters)) {
                // Twitter doesn't always work with oauth in the body and in the header
                // and it's already included in the $authorization header
                unset($parameters['oauth_verifier']);
            }
        } else {
            $authorization = 'Authorization: Bearer ' . $this->bearer;
        }
        return $this->request($request->getNormalizedHttpUrl(), $method, $authorization, $parameters);
    }

    private function curlOptions()
    {
        $options = [
            // CURLOPT_VERBOSE => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectionTimeout,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
        ];
        if ($this->useCAFile()) {
            $options[CURLOPT_CAINFO] = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem';
        }
        if ($this->gzipEncoding) {
            $options[CURLOPT_ENCODING] = 'gzip';
        }
        if (!empty($this->proxy)) {
            $options[CURLOPT_PROXY] = $this->proxy['CURLOPT_PROXY'];
            $options[CURLOPT_PROXYUSERPWD] = $this->proxy['CURLOPT_PROXYUSERPWD'];
            $options[CURLOPT_PROXYPORT] = $this->proxy['CURLOPT_PROXYPORT'];
            $options[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
            $options[CURLOPT_PROXYTYPE] = CURLPROXY_HTTP;
        }
        return $options;
    }

    private function request($url, $method, $authorization, array $postfields)
    {
        $options = $this->curlOptions();
        $options[CURLOPT_URL] = $url;
        $options[CURLOPT_HTTPHEADER] = ['Accept: application/json', $authorization, 'Expect:'];
        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = Util::buildHttpQuery($postfields);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'PUT':
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
                break;
        }
        if (in_array($method, ['GET', 'PUT', 'DELETE']) && !empty($postfields)) {
            $options[CURLOPT_URL] .= '?' . Util::buildHttpQuery($postfields);
        }
        $curlHandle = curl_init();
        curl_setopt_array($curlHandle, $options);
        $response = curl_exec($curlHandle);
        // Throw exceptions on cURL errors.
        if (curl_errno($curlHandle) > 0) {
            throw new TwitterOAuthException(curl_error($curlHandle), curl_errno($curlHandle));
        }
        $this->response->setHttpCode(curl_getinfo($curlHandle, CURLINFO_HTTP_CODE));
        $parts = explode("\r\n\r\n", $response);
        $responseBody = array_pop($parts);
        $responseHeader = array_pop($parts);
        $this->response->setHeaders($this->parseHeaders($responseHeader));
        curl_close($curlHandle);
        return $responseBody;
    }

    private function parseHeaders($header)
    {
        $headers = [];
        foreach (explode("\r\n", $header) as $line) {
            if (strpos($line, ':') !== false) {
                list ($key, $value) = explode(': ', $line);
                $key = str_replace('-', '_', strtolower($key));
                $headers[$key] = trim($value);
            }
        }
        return $headers;
    }

    private function encodeAppAuthorization(Consumer $consumer)
    {
        $key = rawurlencode($consumer->key);
        $secret = rawurlencode($consumer->secret);
        return base64_encode($key . ':' . $secret);
    }

    private function pharRunning()
    {
        return class_exists('Phar') && \Phar::running(false) !== '';
    }

    private function useCAFile()
    {
        /* Use CACert file when not in a PHAR file. */
        return !$this->pharRunning();
    }
}