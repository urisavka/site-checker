<?php

namespace SiteChecker;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface CheckObserver
 * @package SiteChecker
 */
interface SiteCheckObserver
{

    /**
     * We are about to check a page. Should we?
     *
     * @param \SiteChecker\Asset $url
     * @return bool Whether this page should be checked or not.
     */
    public function pageToCheck(Asset $url);


    /**
     * Page was checked. Here we have a response.
     *
     * @param \SiteChecker\Asset $asset
     * @param ResponseInterface $response
     * @return mixed
     */
    public function pageChecked(Asset $asset, ResponseInterface $response = null);


    /**
     * Here we have all checked pages.
     *
     * @param Asset[] $assets
     */
    public function receiveResults(array $assets);
}
