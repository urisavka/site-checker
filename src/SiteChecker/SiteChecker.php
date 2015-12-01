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

    public static $CODES_ERROR = [404, 403];
    public static $CODES_WARNING = [301];

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
     * @var Config
     */
    protected $config;


    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param Config $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * SiteChecker constructor.
     * @param \GuzzleHttp\Client $client
     * @param \Psr\Log\LoggerInterface|null $logger
     */
    public function __construct(Client $client, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->client = $client;
        $this->config = new Config();
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

        if (!$this->isExternal($link) && $this->isHtmlPage($response)) {
            $this->checkAllLinks($response->getBody()->getContents(), $link);
        }

    }

    protected function isExternal(Link $link)
    {
        return $this->baseUrl->host !== $link->host;
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

        $linkElements = $domCrawler->filterXpath('//a');
        $links = [];
        /** @var \DOMElement $linkElement */
        foreach ($linkElements as $linkElement) {
            $links[] = new Link(
              $linkElement->getAttribute('href'), $parentPage,
              $linkElement->ownerDocument->saveHTML($linkElement)
            );
        }
        return $links;
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
        $messages = ['Checking'];
        if ($this->isExternal($link)) {
            $messages [] = 'external';
        }
        $messages [] = 'resource:' . $link->getURL();
        if ($parent = $link->getParentPage()) {
            $messages [] = 'on a page: ' . $parent->getURL() . '.';
        }
        if ($this->config->showFullTags && $html = $link->getFullHtml()) {
            $messages [] = 'Full html of it is: ' . $html . '.';
        }
        $messages [] = 'Received code: ' . $code;
        $message = implode(' ', $messages);
        $this->messages[] = $message;
        if (in_array($code, self::$CODES_ERROR)) {
            $this->logger->error($message);
        } elseif (in_array($code, self::$CODES_WARNING)) {
            $this->logger->warning($message);
        } else {
            $this->logger->info($message);
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
