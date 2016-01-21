<?php

namespace SiteChecker;

/**
 * General class for pages, CSS, JS, images and whatever that can be downloaded.
 * Class Asset
 * @package SiteChecker
 */
class Asset
{

    const CODE_ERROR = 500;
    public static $CODES_ERROR = [404, 403, 500, 503];
    public static $CODES_WARNING = [301];

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
     * @var
     */
    public $query;

    /**
     * @var Asset
     */
    public $parentPage;

    /**
     * @var string
     */
    private $fullHtml;

    /**
     * @var string
     */
    private $responseCode;

    /**
     * @var string
     */
    private $type;


    /**
     * Asset constructor.
     *
     * @param string $url
     * @param Asset $parentPage
     * @param $fullHtml
     * @param $type
     */
    public function __construct(
        $url,
        $parentPage = null,
        $fullHtml = '',
        $type = 'page'
    ) {
        $urlProperties = parse_url($url);

        foreach (['scheme', 'host', 'path', 'port', 'query'] as $property) {
            if (isset($urlProperties[$property])) {
                $this->$property = $urlProperties[$property];
            }
        }
        $this->parentPage = $parentPage;
        $this->fullHtml = $fullHtml;
        $this->type = $type;
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
    public function isHttp()
    {
        // Empty scheme usually means http
        return in_array(
            $this->scheme,
            ['http', 'https', '']
        );
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
     * @return Asset
     */
    public function getParentPage()
    {
        return $this->parentPage;
    }

    /**
     * @param Asset $parentPage
     */
    public function setParentPage($parentPage)
    {
        $this->parentPage = $parentPage;
    }


    /**
     * @return string
     */
    public function getResponseCode()
    {
        return $this->responseCode;
    }

    /**
     * @param string $responseCode
     */
    public function setResponseCode($responseCode)
    {
        $this->responseCode = $responseCode;
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
    public function getFullHtml()
    {
        return $this->fullHtml;
    }

    /**
     * @param string $fullHtml
     */
    public function setFullHtml($fullHtml)
    {
        $this->fullHtml = $fullHtml;
    }

    /**
     * @return string
     */
    public function getURL()
    {
        $path = strpos($this->path, '/') === 0 ?
            substr($this->path, 1) : $this->path;

        $port = ($this->port === 80 ? '' : ":{$this->port}");
        $url = "{$this->scheme}://{$this->host}{$port}/{$path}";

        if ($this->query) {
            $url .= "?{$this->query}";
        }

        return $url;
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

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return !$this->isError();
    }

    /**
     * @return bool
     */
    public function isError()
    {
        return in_array($this->responseCode, self::$CODES_ERROR);
    }

    /**
     * @return bool
     */
    public function isWarning()
    {
        return in_array($this->responseCode, self::$CODES_WARNING);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return null|string
     */
    public function getPath()
    {
        return $this->path;
    }

}
