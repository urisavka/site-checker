<?php

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
    public $showFullTags = false;
    public $showOnlyProblems = false;
    public $checkExternal = false;
    public $reportEmail = null;
    public $reportEmailFrom = null;
    /**
     * It's not okay to have links with whitespaces, but browsers
     * usually fix it, so let's ignore it in most cases
     */
    public $ignoreWhiteSpaces = true;
    public $cookies = [];
    public $excludedUrls = [];
    public $includedUrls = [];

    /**
     * @return array
     */
    public function getCookies()
    {
        $cookies = [];
        foreach ($this->cookies as $cookie) {
            $cookies[] = (array)$cookie;
        }
        return (array)$cookies;
    }

    /**
     * @return array
     */
    public function getReportEmailAddresses()
    {
        if (stristr($this->reportEmail, ',')) {
            return explode(',', $this->reportEmail);

        } else {
            return [$this->reportEmail];
        }
    }

    /**
     * @return string
     */
    public function getMailFrom()
    {
        if (!empty($this->reportEmailFrom)) {
            return $this->reportEmailFrom;
        } else {
            $addresses = $this->getReportEmailAddresses();
            return $addresses[0];
        }

    }
}
