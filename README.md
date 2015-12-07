# Site Checker
Check site for broken links, missing images, css and javascript files.

# Installation
Clone repository and run ``composer install``

# Configuration
Copy ``config/app.json.default`` into ``config/app.json`` and add whatever you want there.

Could be parameters, cookies or excluded URLs.

If you define ``reportEmail`` parameter, email will be sent with a list of broken list. 
You can also define ``reportEMailFrom`` value to set ``From:`` field.

# Usage
Console tool: ``sitechecker [-e|--check-external] [-s|--log-success] [-f|--full-html] [--] <site>``
From your code:
```PHP
$siteChecker = SiteChecker::create();
$siteChecker->check('http://gooogle.com');
$results = $siteChecker->getResults();
```
See CheckCommand for real usage with Observer.
