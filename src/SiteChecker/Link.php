<?php

namespace SiteChecker;

class Link
{
    /**
     * @var null|string
     */
    public $scheme;

    /**
     * @var null|string
     */
    public $host;

    /**
     * @var int
     */
    public $port = 80;

    /**
     * @var null|string
     */
    public $path;

    /**
     * @var Link
     */
    public $parentPage;

    /**
     * Url constructor.
     *
     * @param string $url
     * @param Link $parentPage
     */
    public function __construct($url, $parentPage = null)
    {
        $urlProperties = parse_url($url);

        foreach (['scheme', 'host', 'path', 'port'] as $property) {
            if (isset($urlProperties[$property])) {
                $this->$property = $urlProperties[$property];
            }
        }
        $this->parentPage = $parentPage;
    }

    /**
     * Determine if the url is relative.
     *
     * @return bool
     */
    public function isRelative()
    {
        return is_null($this->host);
    }

    /**
     * Determine if the url is protocol independent.
     *
     * @return bool
     */
    public function isProtocolIndependent()
    {
        return is_null($this->scheme);
    }

    /**
     * Determine if this is a mailto-link.
     *
     * @return bool
     */
    public function isEmailUrl()
    {
        return $this->scheme === 'mailto';
    }

    /**
     * Set the scheme.
     *
     * @param string $scheme
     *
     * @return $this
     */
    public function setScheme($scheme)
    {
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * Set the host.
     *
     * @param string $host
     *
     * @return $this
     */
    public function setHost($host)
    {
        $this->host = $host;

        return $this;
    }

    /**
     * @param int $port
     *
     * @return $this
     *
     */
    public function setPort($port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * @return Link
     */
    public function getParentPage()
    {
        return $this->parentPage;
    }

    /**
     * @param Link $parentPage
     */
    public function setParentPage($parentPage)
    {
        $this->parentPage = $parentPage;
    }

    /**
     * Remove the fragment.
     *
     * @return $this
     */
    public function removeFragment()
    {
        $this->path = explode('#', $this->path)[0];

        return $this;
    }

    /**
     * @return string
     */
    public function getURL() {
        $path = strpos($this->path, '/') === 0 ? substr($this->path, 1) : $this->path;

        $port = ($this->port === 80 ? '' : ":{$this->port}");

        return "{$this->scheme}://{$this->host}{$port}/{$path}";
    }

    /**
     * Convert the url to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getURL();
    }
}
