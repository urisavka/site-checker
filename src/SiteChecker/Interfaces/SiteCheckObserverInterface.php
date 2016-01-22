<?php

namespace SiteChecker\Interfaces;

use SiteChecker\Asset;

/**
 * Interface CheckObserver
 * @package SiteChecker
 */
interface SiteCheckObserverInterface
{

    /**
     * We are about to check a page. Should we?
     *
     * @param Asset $url
     * @return bool Whether this page should be checked or not.
     */
    public function pageToCheck(Asset $url);


    /**
     * Page was checked. Here we have a response.
     *
     * @param Asset $asset
     * @return mixed
     */
    public function pageChecked(
        Asset $asset
    );


    /**
     * Here we have all checked pages.
     *
     * @param Asset[] $assets
     */
    public function receiveResults(array $assets);
}
