<?php
namespace SiteChecker;


use SiteChecker\Interfaces\SiteCheckObserverInterface;

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
     * @return mixed
     */
    public function pageChecked(Asset $asset)
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
