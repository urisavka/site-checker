<?php

namespace SiteChecker\Interfaces;

use SiteChecker\Asset;

/**
 * Interface AssetExtractor
 * @package SiteChecker
 */
interface ContentExtractorInterface
{
    /**
     * @param Asset $asset
     */
   public function extractContent(Asset $asset);
}
