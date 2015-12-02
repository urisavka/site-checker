<?php

namespace SiteChecker;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
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
     * @var Asset[]
     */
    protected $checkedAssets = [];

    /**
     * @var Asset
     */
    protected $basePage;

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
     * Check the site for broken assets.
     *
     * @param string $baseUrl
     */
    public function check($baseUrl)
    {
        if (!$baseUrl instanceof Asset) {
            $baseUrl = new Asset($baseUrl);
        }
        $this->messages = [];
        $this->basePage = $baseUrl;

        $this->checkAsset($baseUrl);
        $this->checkResults();
    }


    /**
     * @param Asset $asset
     */
    protected function checkAsset(Asset $asset)
    {
        if (!$this->shouldBeChecked($asset)) {
            return;
        }
        $cookies = $this->config->getCookies();

        foreach ($cookies as $key => $cookie) {
            $cookie['Domain'] = $this->basePage->host;
            $cookies[$key] = new SetCookie($cookie);
        }

        $jar = new CookieJar(false, $cookies);

        try {
            $response = $this->client->request('GET', $asset->getURL(),
              ['cookies' => $jar]);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $asset->setResponseCode('500');
        }

        if ($response) {
            $asset->setResponseCode($response->getStatusCode());
        }

        $this->logResult($asset);

        $this->checkedAssets[] = $asset;

        if (!$response) {
            return;
        }

        if (!$this->isExternal($asset) && $this->isHtmlPage($response)) {
            $this->checkAllAssets($response->getBody()->getContents(), $asset);
        }

    }

    /**
     * @param Asset $asset
     * @return bool
     */
    protected function isExternal(Asset $asset)
    {
        return $this->basePage->host !== $asset->host;
    }

    /**
     * Crawl all assets in the given html.
     *
     * @param string $html
     * @param Asset $parentAsset
     */
    protected function checkAllAssets($html, $parentAsset)
    {
        $allAssets = $this->getAllAssets($html, $parentAsset);

        /** @var Asset $asset */
        foreach ($allAssets as $asset) {
            if (!$asset->isEmailUrl()) {
                $this->normalizeUrl($asset);
                if ($this->shouldBeChecked($asset)) {
                    $this->checkAsset($asset);
                }
            }
        }

    }

    /**
     * Crawl all assets in the given html.
     *
     * @param $html
     * @param $parentPage
     * @return array
     */
    protected function getAllAssets($html, $parentPage)
    {
        $assets = [];

        $assets = array_merge(
          $assets,
          $this->createAssetsFromDOMElements(
            $html, '//a', 'href', 'page', $parentPage
          )
        );

        if ($this->config->checkImages) {
            $assets = array_merge(
              $assets,
              $this->createAssetsFromDOMElements(
                $html, '//img', 'src', 'css file', $parentPage
              )
            );
        }

        if ($this->config->checkJS) {
            $assets = array_merge(
              $assets,
              $this->createAssetsFromDOMElements(
                $html, '//script', 'src', 'js file', $parentPage
              )
            );
        }

        if ($this->config->checkCSS) {
            $assets = array_merge(
              $assets,
              $this->createAssetsFromDOMElements(
                $html, '//link[@rel="stylesheet"]', 'href', 'image', $parentPage
              )
            );
        }

        return $assets;
    }

    /**
     * @param $html
     * @param $selector
     * @param $urlAttribute
     * @param $type
     * @param $parentPage
     * @return array
     */
    protected function createAssetsFromDOMElements(
      $html,
      $selector,
      $urlAttribute,
      $type,
      $parentPage
    ) {
        $assets = [];

        $crawler = new Crawler($html);
        $elements = $crawler->filterXpath($selector);

        /** @var \DOMElement $assetElement */
        foreach ($elements as $element) {
            if (!empty($element->getAttribute($urlAttribute))) {
                $urlValue = $element->getAttribute($urlAttribute);
                if ($this->config->ignoreWhiteSpaces) {
                    $urlValue = trim($urlValue);
                }

                $assets[] = new Asset(
                  $urlValue,
                  $parentPage,
                  $element->ownerDocument->saveHTML($element),
                  $type
                );
            }
        }

        return $assets;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return bool
     */
    protected function isHtmlPage(ResponseInterface $response)
    {
        foreach ($response->getHeader('content-type') as $header) {
            if (stristr($header, 'text/html') !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Called when the crawler has crawled the given url.
     *
     * @param Asset $asset
     */
    public function logResult($asset)
    {
        $code = $asset->getResponseCode();
        $messageParts = ['Checking'];
        if ($this->isExternal($asset)) {
            $messageParts[] = 'external';
        }
        $messageParts[] = 'asset: ' . $asset->getURL();
        if ($parent = $asset->getParentPage()) {
            $messageParts[] = 'on a page: ' . $parent->getURL() . '.';
        }
        if ($this->config->showFullTags && $html = $asset->getFullHtml()) {
            $messageParts[] = 'Full html of it is: ' . $html . '.';
        }
        $messageParts[] = 'Received code: ' . $code;
        $message = implode(' ', $messageParts);

        $this->messages[] = $message;
        if ($asset->isError()) {
            $this->logger->error($message);
        } elseif ($asset->isWarning()) {
            $this->logger->warning($message);
        } else {
            $this->logger->info($message);
        }
    }

    /**
     * @param Asset $asset
     * @return bool
     */
    protected function shouldBeChecked(Asset $asset)
    {
        if (!$this->config->checkExternal && $this->isExternal($asset)) {
            return false;
        }
        return !in_array($asset->getUrl(), $this->checkedAssets);
    }

    /**
     * @param \SiteChecker\Asset $asset
     * @return bool
     */
    protected function isAlreadyChecked(Asset $asset)
    {
        return in_array($asset->getURL(), $this->checkedAssets);
    }

    /**
     * Called when the crawl has ended.
     */
    public function checkResults()
    {
        $this->logger->info("Check is finished. Here are the results:");
        $successCount = 0;
        $failedCount = 0;

        foreach ($this->checkedAssets as $asset) {
            if ($asset->isSuccessful()) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }
        if ($successCount) {
            $this->logger->info('Successful: ' . $successCount);
        }
        if ($failedCount) {
            $this->logger->error('Failed: ' . $failedCount);
        }
    }

    /**
     * Normalize the given url.
     * @param \SiteChecker\Asset $asset
     * @return $this
     */
    protected function normalizeUrl(Asset $asset)
    {
        if ($asset->isRelative()) {

            $asset->setScheme($this->basePage->scheme)
              ->setHost($this->basePage->host)
              ->setPort($this->basePage->port);
        }

        if ($asset->isProtocolIndependent()) {
            $asset->setScheme($this->basePage->scheme);
        }

        return $asset->removeFragment();
    }

}
