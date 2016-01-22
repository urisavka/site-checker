# Site Checker 
[![Build Status](https://travis-ci.org/urisavka/site-checker.svg?branch=master)](https://travis-ci.org/urisavka/site-checker) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/urisavka/site-checker/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/urisavka/site-checker/?branch=master)

Recursively checks site for broken links, missing images, css and javascript files starting from giving URL and discovering all links on pages.

# Installation
## For console usage
> git clone git@github.com:urisavka/site-checker.git && cd site-checker && composer update --no-dev

## For usage in a project
> composer require urisavka/site-checker

# Configuration
Copy ``config/app.json.default`` into ``config/app.json`` and add whatever you want there.

You could also specify custom cookies to be sent with request.

Excluded URLs can be defined as regular expressions in PCRE format. Use ``excludedUrls`` option.

You may also include set of custom included Urls that are not accessible from your home page. Use ``includedUrls`` option.

If you define ``reportEmail`` parameter, email will be sent after checking with a list of broken links (if any). 
You can also define ``reportEMailFrom`` value to set ``From:`` field for your emails.

See ``config/app.json.default`` for example.

# Usage
Console tool: ``sitechecker [-e|--check-external] [-s|--log-success] [-f|--full-html] [--] <site>``

From your code:
```PHP
$siteChecker = SiteChecker::create();
$siteChecker->check('http://gooogle.com');
$results = $siteChecker->getResults();
```

See CheckCommand and ConsoleObserver for real usage.
