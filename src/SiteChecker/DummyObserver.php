<?php
namespace SiteChecker;


use Psr\Http\Message\ResponseInterface;

class DummyObserver implements SiteCheckObserverInterface
{

    /**
     * No additional checks here for now.
     *
     * @param \SiteChecker\Asset $url
     * @return bool
     */
    public function pageToCheck(Asset $url)
    {
        return true;
    }


    /**
     * Do nothing.
     *
     * @param \SiteChecker\Asset $asset
     * @param $response
     * @return mixed
     */
    public function pageChecked(Asset $asset, ResponseInterface $response = null)
    {

    }


    /**
     * Do nothing again.
     *
     * @param Asset[] $assets
     */
    public function receiveResults(array $assets)
    {

    }
}
