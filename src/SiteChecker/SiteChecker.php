<?php

namespace SiteChecker;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Spatie\Crawler\Crawler;
use Spatie\Crawler\CrawlObserver;
use Spatie\Crawler\CrawlProfile;
use Spatie\Crawler\Url;

/**
 * Class SiteChecker
 * @package SiteChecker
 */
class SiteChecker implements CrawlObserver, CrawlProfile
{
    /**
     * @var \Spatie\Crawler\Crawler
     */
    protected $crawler;

    /**
     * @var array
     */
    protected $messages = [];

    /**
     * @var string
     */
    protected $host;

    /**
     * @var Logger
     */
    protected $logger;


    public function __construct(Crawler $crawler)
    {
        $crawler->setCrawlObserver($this)
          ->setCrawlProfile($this);
        $this->crawler = $crawler;
        $this->logger = new Logger('site-checker-responses');
        $this->logger->pushHandler(new StreamHandler('log/parsing.log', Logger::INFO));
    }

    /**
     * @return static
     */
    public static function create()
    {
        $client = new Client([
          RequestOptions::ALLOW_REDIRECTS => true,
          RequestOptions::COOKIES => true,
        ]);
        $crawler = new Crawler($client);
        return new static($crawler);
    }

    /**
     * Check the site for broken links.
     *
     * @param string $baseUrl
     */
    public function check($baseUrl)
    {
        $urlProperties = parse_url($baseUrl);
        $this->host = $urlProperties['host'];

        $this->messages = [];
        $this->crawler->startCrawling($baseUrl);
    }

    /**
     * Called when the crawler will crawl the url.
     *
     * @param \Spatie\Crawler\Url $url
     */
    public function willCrawl(Url $url)
    {

    }

    /**
     * Called when the crawler has crawled the given url.
     *
     * @param \Spatie\Crawler\Url $url
     * @param \Psr\Http\Message\ResponseInterface|null $response
     */
    public function hasBeenCrawled(Url $url, $response)
    {
        $message = 'Parsing ' . $url . '. Received code: ' . $response->getStatusCode();
        $this->messages[] = $message;
        $this->logResult($message);
    }

    protected function logResult($message) {
        $this->logger->addInfo($message);
    }

    /**
     * Called when the crawl has ended.
     */
    public function finishedCrawling()
    {
        $this->logger->addInfo("Crawling was finished");
    }

    /**
     * Crawl only links to existing site.
     *
     * @param \Spatie\Crawler\Url $url
     *
     * @return bool
     */
    public function shouldCrawl(Url $url)
    {
        return $url->host == $this->host;
    }

    /**
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }
}
