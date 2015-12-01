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
     * @var Link
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
        if (!$baseUrl instanceof Link) {
            $baseUrl = new Link($baseUrl);
        }
        $this->messages = [];
        $this->baseUrl = $baseUrl;

        $this->checkLink($baseUrl);
    }

    /**
     * @param Link $link
     */
    protected function checkLink(Link $link)
    {
        if (!$this->shouldBeChecked($link)) {
            return;
        }

        try {
            $response = $this->client->request('GET', $link->getURL());
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
        }

        $this->logResult($link, $response);

        $this->checkedUrls[] = $link;

        if (!$response) {
            return;
        }

        if ($this->baseUrl->host === $link->host && $this->isHtmlPage($response)) {
            $this->checkAllLinks($response->getBody()->getContents(), $link);
        }

    }

    /**
     * Crawl all links in the given html.
     *
     * @param string $html
     * @param $parentLink
     */
    protected function checkAllLinks($html, $parentLink)
    {
        $allLinks = $this->getAllLinks($html, $parentLink);

        /** @var Link $link */
        foreach ($allLinks as $link) {
            if (!$link->isEmailUrl()) {
                $this->normalizeUrl($link);
                if ($this->shouldBeChecked($link)) {
                    $this->checkLink($link);
                }
            }
        }

    }

    /**
     * Crawl all links in the given html.
     *
     * @param $html
     * @param $parentPage
     * @return array
     */
    protected function getAllLinks($html, $parentPage)
    {
        $domCrawler = new Crawler($html);

        $urls = $domCrawler->filterXpath('//a')
          ->extract(['href']);
        return array_map(function ($url) use ($parentPage) {
            return new Link($url, $parentPage);
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
     * @param Link $link
     * @param \Psr\Http\Message\ResponseInterface|null $response
     */
    public function logResult(Link $link, $response)
    {
        $code = $response->getStatusCode();
        $message = 'Parsing ' . $link->getURL();
        if ($parent = $link->getParentPage()) {
            $message .= ' on a page: ' . $parent->getURL() . '.';
        }
        $message .= ' Received code: ' . $code;
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
     * @param \SiteChecker\Link $link
     * @return bool
     */
    protected function shouldBeChecked(Link $link)
    {
        return !in_array($link->getUrl(), $this->checkedUrls);
    }

    /**
     * @param \SiteChecker\Link $link
     * @return bool
     */
    protected function isAlreadyChecked(Link $link)
    {
        return in_array($link->getURL(), $this->checkedUrls);
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
     * @param \SiteChecker\Link $link
     * @return $this
     */
    protected function normalizeUrl(Link $link)
    {
        if ($link->isRelative()) {

            $link->setScheme($this->baseUrl->scheme)
              ->setHost($this->baseUrl->host)
              ->setPort($this->baseUrl->port);
        }

        if ($link->isProtocolIndependent()) {
            $link->setScheme($this->baseUrl->scheme);
        }

        return $link->removeFragment();
    }

}
