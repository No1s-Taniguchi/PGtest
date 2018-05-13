<?php

class Request
{
    protected $parameters;
    protected $httpMethod;
    protected $httpUrl;
    public static $version = '1.0';

    public function __construct($httpMethod, $httpUrl, array $parameters = [])
    {
        $parameters = array_merge(Util::parseParameters(parse_url($httpUrl, PHP_URL_QUERY)), $parameters);
        $this->parameters = $parameters;
        $this->httpMethod = $httpMethod;
        $this->httpUrl = $httpUrl;
    }

    public static function fromConsumerAndToken(
        Consumer $consumer,
        Token $token = null,
        $httpMethod,
        $httpUrl,
        array $parameters = []
    ) {
        $defaults = [
            "oauth_version" => Request::$version,
            "oauth_nonce" => Request::generateNonce(),
            "oauth_timestamp" => time(),
            "oauth_consumer_key" => $consumer->key
        ];
        if (null !== $token) {
            $defaults['oauth_token'] = $token->key;
        }
        $parameters = array_merge($defaults, $parameters);
        return new Request($httpMethod, $httpUrl, $parameters);
    }

    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    public function getParameter($name)
    {
        return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function removeParameter($name)
    {
        unset($this->parameters[$name]);
    }

    public function getSignableParameters()
    {
        // Grab all parameters
        $params = $this->parameters;
        // Remove oauth_signature if present
        // Ref: Spec: 9.1.1 ("The oauth_signature parameter MUST be excluded.")
        if (isset($params['oauth_signature'])) {
            unset($params['oauth_signature']);
        }
        return Util::buildHttpQuery($params);
    }

    public function getSignatureBaseString()
    {
        $parts = [
            $this->getNormalizedHttpMethod(),
            $this->getNormalizedHttpUrl(),
            $this->getSignableParameters()
        ];
        $parts = Util::urlencodeRfc3986($parts);
        return implode('&', $parts);
    }

    public function getNormalizedHttpMethod()
    {
        return strtoupper($this->httpMethod);
    }

    public function getNormalizedHttpUrl()
    {
        $parts = parse_url($this->httpUrl);
        $scheme = $parts['scheme'];
        $host = strtolower($parts['host']);
        $path = $parts['path'];
        return "$scheme://$host$path";
    }

    public function toUrl()
    {
        $postData = $this->toPostdata();
        $out = $this->getNormalizedHttpUrl();
        if ($postData) {
            $out .= '?' . $postData;
        }
        return $out;
    }

    public function toPostdata()
    {
        return Util::buildHttpQuery($this->parameters);
    }

    public function toHeader()
    {
        $first = true;
        $out = 'Authorization: OAuth';
        foreach ($this->parameters as $k => $v) {
            if (substr($k, 0, 5) != "oauth") {
                continue;
            }
            if (is_array($v)) {
                throw new TwitterOAuthException('Arrays not supported in headers');
            }
            $out .= ($first) ? ' ' : ', ';
            $out .= Util::urlencodeRfc3986($k) . '="' . Util::urlencodeRfc3986($v) . '"';
            $first = false;
        }
        return $out;
    }

    public function __toString()
    {
        return $this->toUrl();
    }

    public function signRequest(SignatureMethod $signatureMethod, Consumer $consumer, Token $token = null)
    {
        $this->setParameter("oauth_signature_method", $signatureMethod->getName());
        $signature = $this->buildSignature($signatureMethod, $consumer, $token);
        $this->setParameter("oauth_signature", $signature);
    }

    public function buildSignature(SignatureMethod $signatureMethod, Consumer $consumer, Token $token = null)
    {
        return $signatureMethod->buildSignature($this, $consumer, $token);
    }

    public static function generateNonce()
    {
        return md5(microtime() . mt_rand());
    }
}