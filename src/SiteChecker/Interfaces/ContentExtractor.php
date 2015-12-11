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
interface ContentExtractor
{
    /**
     * @param Asset $asset
     * @return Asset[]
     */
   public function extractContent(Asset $asset);
}
