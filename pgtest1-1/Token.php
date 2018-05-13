<?php

class Token
{
    public $key;
    public $secret;

    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }

    public function __toString()
    {
        return sprintf("oauth_token=%s&oauth_token_secret=%s",
            Util::urlencodeRfc3986($this->key),
            Util::urlencodeRfc3986($this->secret)
        );
    }
}