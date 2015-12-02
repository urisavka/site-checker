# Site Checker
Check site for broken links, missing images, css and javascript files.

# Installation
Clone repository and run ``composer install``

# Configuration
Copy ``config/app.json.default`` into ``config/app.json`` and add whatever you want there.
Could be parameters or specific cookies to use.

# Usage
Console tool: ``sitechecker [-e|--check-external] [-s|--log-success] [-f|--full-html] [--] <site>``
From your code:
```PHP
$logger = new ConsoleLogger($output, $verbosityLevelMap); // Whatever logger that supports ->info() and ->error() methods
$siteChecker = SiteChecker::create($logger);
$siteChecker->check('http://gooogle.com');
```
