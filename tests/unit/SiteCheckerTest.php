<?php

namespace SiteChecker\Test;

use SiteChecker\Asset;
use SiteChecker\SiteChecker;

class SiteCheckerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Use reflection to test protected methods.
     *
     * @param $obj
     * @param $name
     * @param array $args
     * @return mixed
     */
    protected static function callMethod($obj, $name, array $args)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }

    /**
     * @test
     */
    public function testParser()
    {
        $siteChecker = SiteChecker::create();
        $html = '<a href="/xxx"></a><img src="/yyy">';

        /** @var Asset[] $assets */
        $assets = self::callMethod(
            $siteChecker,
            'getAllAssets',
            array($html, null)
        );

        $link = $assets[0];
        $this->assertEquals('/xxx', $link->getPath());
        $this->assertEquals('page', $link->getType());

        $image = $assets[1];
        $this->assertEquals('/yyy', $image->getPath());
        $this->assertEquals('image', $image->getType());
    }
}
