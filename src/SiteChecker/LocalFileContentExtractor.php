<?php
/**
 * @author Rocket Internet SE
 * @copyright Copyright (c) 2015 Rocket Internet SE, Johannisstraße 20, 10117 Berlin, http://www.rocket-internet.com
 * @created 22/01/16 15:09
 */

namespace SiteChecker;


use SiteChecker\Interfaces\ContentExtractorInterface;

class LocalFileContentExtractor implements ContentExtractorInterface
{
    /**
     * @param Asset $asset
     */
    public function extractContent(Asset $asset)
    {
        //@todo: check how to change asset to be able to fetch local files.
        $asset->setContents('*_*');
    }
}
