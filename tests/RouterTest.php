<?php

use RainLab\Pages\Classes\Router;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * RouterTest covers static page URL resolution
 */
class RouterTest extends PagesPluginTestCase
{
    public function testFindByUrl()
    {
        $router = new Router($this->theme);

        $page = $router->findByUrl('/');
        $this->assertNotNull($page);
        $this->assertEquals('index', $page->getBaseFileName());

        $page = $router->findByUrl('/about');
        $this->assertEquals('about', $page->getBaseFileName());

        $page = $router->findByUrl('/about/team');
        $this->assertEquals('about-team', $page->getBaseFileName());
    }

    public function testFindByUrlIsCaseInsensitive()
    {
        $router = new Router($this->theme);

        $page = $router->findByUrl('/About');
        $this->assertNotNull($page);
        $this->assertEquals('about', $page->getBaseFileName());
    }

    public function testFindByUrlReturnsNullForUnknownUrl()
    {
        $router = new Router($this->theme);

        $this->assertNull($router->findByUrl('/missing'));
    }
}
