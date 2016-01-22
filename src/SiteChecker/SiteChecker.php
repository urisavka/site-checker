<?php

namespace SiteChecker;

use SiteChecker\Interfaces\SiteCheckObserverInterface;
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
     * @var SiteCheckObserverInterface
     */
    protected $observer;

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
     * @param SiteCheckObserverInterface|null $observer
     */
    public function __construct(
        SiteCheckObserverInterface $observer = null
    ) {
        $this->observer = $observer ?: new DummyObserver();
        $this->config = new Config();
    }


    /**
     * @param SiteCheckObserverInterface $observer
     * @return static
     */
    public static function create(SiteCheckObserverInterface $observer = null)
    {
        return new static($observer);
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
            if ($this->shouldBeChecked($asset) && $this->observer->pageToCheck($asset)) {
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
        $contentExtractor = new HttpClientExtractor($this->config);
        $contentExtractor->extractContent($asset);

        $this->observer->pageChecked($asset);

        $this->checkedAssets[] = $asset;

        if ($asset->isSuccessful() && !$this->isExternal($asset)) {
            $this->checkAllAssets($asset);
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
     * @param Asset $parentAsset
     */
    protected function checkAllAssets($parentAsset)
    {
        $html = $parentAsset->getContents();
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

        foreach ($assetTypes as $args) {
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
