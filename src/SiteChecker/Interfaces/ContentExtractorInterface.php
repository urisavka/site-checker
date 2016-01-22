<?php
/**
 * @author Rocket Internet SE
 * @copyright Copyright (c) 2015 Rocket Internet GmbH, Johannisstraße 20, 10117 Berlin, http://www.rocket-internet.de
 * @created 10/12/15 12:41
 */

namespace SiteChecker;

/**
 * Interface AssetExtractor
 * @package SiteChecker
 */
interface ContentExtractorInterface
{
    /**
     * @param Asset $asset
     * @return string
     */
   public function extractContent(Asset $asset);
}
