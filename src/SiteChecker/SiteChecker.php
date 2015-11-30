<?php

namespace SiteChecker;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    protected $logger;


    /**
     * SiteChecker constructor.
     * @param \Spatie\Crawler\Crawler $crawler
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(
      Crawler $crawler,
      LoggerInterface $logger = null
    ) {
        $crawler->setCrawlObserver($this)
          ->setCrawlProfile($this);
        $this->crawler = $crawler;
        $this->logger = $logger;
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
        $crawler = new Crawler($client);

        return new static($crawler, $logger);
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
     * Called when the crawl has ended.
     */
    public function finishedCrawling()
    {
        $this->logger->info("Crawling was finished");
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
