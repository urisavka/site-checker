<?php

namespace SiteChecker;


use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RedirectMiddleware;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use SiteChecker\Interfaces\ContentExtractorInterface;

class HttpClientExtractor implements ContentExtractorInterface
{
    /** @var Config */
    private $config;

    /** @var  ClientInterface */
    private $client;

    /**
     * HttpClientExtractor constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new Client([
            RequestOptions::ALLOW_REDIRECTS => ['track_redirects' => true],
            RequestOptions::COOKIES => true,
            RequestOptions::VERIFY => false,
        ]);
    }


    /**
     * @param Asset $asset
     */
    public function extractContent(Asset $asset)
    {
        $cookies = $this->config->getCookies();

        foreach ($cookies as $key => $cookie) {
            $cookie['Domain'] = $asset->getHost();
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

        $redirects = $response->getHeader(RedirectMiddleware::HISTORY_HEADER);
        if ($redirects) {
            $realUrl = array_pop($redirects);
            // @todo: Set real URL as baseHost instead of starting one.
        }

        if ($asset->getType() == 'page' && !$this->isHtmlPage($response)) {
            $asset->setType('file');
        }

        if ($asset->getType() == 'page') {
            $asset->setContents($response->getBody()->getContents());
        }
    }

    /**
     * @param ResponseInterface $response
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
}
