<?php
/**
 * @author Rocket Internet SE
 * @copyright Copyright (c) 2015 Rocket Internet GmbH, Johannisstraße 20, 10117 Berlin, http://www.rocket-internet.de
 * @created 01/12/15 11:36
 */

namespace SiteChecker;

/**
 * Class Config
 * @package SiteChecker
 */
class Config
{
    public $checkImages = true;
    public $checkCSS = true;
    public $checkJS = true;
    public $checkFonts = true;
    public $showFullTags = false;
    public $showOnlyProblems = false;
    public $checkExternal = false;
    // It's not okay to have links with whitespaces, but browsers
    // usually fix it, so let's ignore it in most cases
    public $ignoreWhiteSpaces = true;
}
