<?php

namespace SiteChecker;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
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
     * @var SiteCheckObserver
     */
    protected $observer;

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
     * @param \SiteChecker\SiteCheckObserver|null $observer
     */
    public function __construct(
        Client $client,
        SiteCheckObserver $observer = null
    ) {
        $this->client = $client;
        $this->observer = $observer ?: new DummyObserver();
        $this->config = new Config();
    }


    /**
     * @param \SiteChecker\SiteCheckObserver $observer
     * @return static
     */
    public static function create(SiteCheckObserver $observer = null)
    {
        $client = new Client([
            RequestOptions::ALLOW_REDIRECTS => true,
            RequestOptions::COOKIES => true,
            RequestOptions::VERIFY => false,
        ]);

        return new static($client, $observer);
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
        foreach ($this->config->includedUrls as $includedUrl) {
            $asset = new Asset($includedUrl);
            $this->normalizeUrl($asset);
            if (!in_array($asset->getUrl(), $this->checkedAssets)) {
                $this->checkAsset($asset);
            }
        }
        $this->observer->receiveResults($this->checkedAssets);
    }


    /**
     * @param Asset $asset
     */
    protected function checkAsset(Asset $asset)
    {
        if (!$this->shouldBeChecked($asset) || !$this->observer->pageToCheck($asset)) {
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
                [
                    'cookies' => $jar,
                ]);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $asset->setResponseCode(Asset::CODE_ERROR);
        }

        if ($response) {
            $asset->setResponseCode($response->getStatusCode());
        }

        $this->observer->pageChecked($asset, $response);

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
            if ($asset->isHttp()) {
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

        $assetTypes = [
            'checkImages' => [
                '//img',
                'src',
                'image',
            ],
            'checkJS' => [
                '//script',
                'src',
                'js file',
            ],
            'checkCSS' => [
                '//link[@rel="stylesheet"]',
                'href',
                'image',
            ],
        ];

        $assets = array_merge(
            $assets,
            $this->createAssetsFromDOMElements(
                $html, '//a', 'href', 'page', $parentPage
            )
        );

        foreach ($assetTypes as $configKey => $args) {
            array_unshift($args, $html);
            $args[] = $parentPage;
            $assets = array_merge(
                $assets,
                call_user_func_array(
                    [$this, "createAssetsFromDOMElements"],
                    $args
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
     * @param Asset $asset
     * @return bool
     */
    protected function shouldBeChecked(Asset $asset)
    {
        if (in_array($asset->getURL(), $this->config->excludedUrls)) {
            return false;
        }
        foreach ($this->config->excludedUrls as $excludedUrl) {
            if (preg_match('/' . $excludedUrl . '/i', $asset->getURL())) {
                return false;
            }
        }
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

    /**
     * @return \SiteChecker\Asset[]
     */
    public function getResults()
    {
        return $this->checkedAssets;
    }

}
