<?php

namespace SiteChecker;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Class SiteChecker
 * @package SiteChecker
 */
class SiteChecker
{

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * @var array
     */
    protected $checkedUrls = [];

    /**
     * @var Url
     */
    protected $baseUrl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Client
     */
    protected $client;


    /**
     * SiteChecker constructor.
     * @param \GuzzleHttp\Client $client
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(Client $client, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->client = $client;
    }


    /**
     * @param \Psr\Log\LoggerInterface|null $logger
     * @return static
     */
    public static function create(LoggerInterface $logger = null)
    {
        $client = new Client([
          RequestOptions::ALLOW_REDIRECTS => true,
          RequestOptions::COOKIES => true,
        ]);


        return new static($client, $logger);
    }

    /**
     * Check the site for broken links.
     *
     * @param string $baseUrl
     */
    public function check($baseUrl)
    {
        if (!$baseUrl instanceof Url) {
            $baseUrl = new Url($baseUrl);
        }
        $this->messages = [];
        $this->baseUrl = $baseUrl;

        $this->checkLink($baseUrl);
    }

    /**
     * @param Url $url
     */
    protected function checkLink(Url $url)
    {
        if (!$this->shouldBeChecked($url)) {
            return;
        }

        try {
            $response = $this->client->request('GET', (string)$url);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
        }

        $this->logResult($url, $response);

        $this->checkedUrls[] = $url;

        if (!$response) {
            return;
        }

        if ($this->baseUrl->host === $url->host && $this->isHtmlPage($response)) {
            $this->checkAllLinks($response->getBody()->getContents());
        }

    }

    /**
     * Crawl all links in the given html.
     *
     * @param string $html
     */
    protected function checkAllLinks($html)
    {
        //@todo: Add parent page here.
        $allLinks = $this->getAllLinks($html);

        /** @var Url $url */
        foreach ($allLinks as $url) {
            if (!$url->isEmailUrl()) {
                $this->normalizeUrl($url);
                if ($this->shouldBeChecked($url)) {
                    $this->checkLink($url);
                }
            }
        }

    }

    /**
     * Crawl all links in the given html.
     *
     * @param $html
     * @return array
     */
    protected function getAllLinks($html)
    {
        $domCrawler = new Crawler($html);

        $urls = $domCrawler->filterXpath('//a')
          ->extract(['href']);
        return array_map(function ($url) {
            return Url::create($url);
        }, $urls);
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return bool
     */
    protected function isHtmlPage(ResponseInterface $response)
    {
        return in_array('text/html', $response->getHeader('content-type'));
    }

    /**
     * Called when the crawler has crawled the given url.
     *
     * @param Url $url
     * @param \Psr\Http\Message\ResponseInterface|null $response
     */
    public function logResult(Url $url, $response)
    {
        $code = $response->getStatusCode();
        $message = 'Parsing ' . $url . '. Received code: ' . $code;
        $this->messages[] = $message;
        switch ($response->getStatusCode()) {
            case 404:
            case 403:
                $this->logger->error($message);
                break;
            case 301:
                $this->logger->warning($message);
                break;
            default:
                $this->logger->info($message);
                break;
        }
    }

    /**
     * @param \SiteChecker\Url $url
     * @return bool
     */
    protected function shouldBeChecked(Url $url)
    {
        return !in_array((string)$url, $this->checkedUrls);
    }

    /**
     * @param \SiteChecker\Url $url
     * @return bool
     */
    protected function isAlreadyChecked(Url $url)
    {
        return in_array((string)$url, $this->checkedUrls);
    }

    /**
     * Called when the crawl has ended.
     */
    public function finishedCrawling()
    {
        $this->logger->info("Crawling was finished");
    }

    /**
     * Normalize the given url.
     * @param \SiteChecker\Url $url
     * @return $this
     */
    protected function normalizeUrl(Url $url)
    {
        if ($url->isRelative()) {

            $url->setScheme($this->baseUrl->scheme)
              ->setHost($this->baseUrl->host)
              ->setPort($this->baseUrl->port);
        }

        if ($url->isProtocolIndependent()) {
            $url->setScheme($this->baseUrl->scheme);
        }

        return $url->removeFragment();
    }

}
