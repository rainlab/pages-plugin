<?php

use RainLab\Pages\Classes\Controller;

require_once __DIR__.'/PagesPluginTestCase.php';

/**
 * ControllerTest covers generating CMS pages from static pages
 */
class ControllerTest extends PagesPluginTestCase
{
    public function testInitCmsPage()
    {
        $cmsPage = Controller::instance()->initCmsPage('/about');

        $this->assertNotNull($cmsPage);
        $this->assertInstanceOf(\Cms\Classes\Page::class, $cmsPage);
        $this->assertArrayHasKey('staticPage', $cmsPage->apiBag);
        $this->assertEquals('About', $cmsPage->settings['title']);
        $this->assertEquals('default', $cmsPage->settings['layout']);
    }

    public function testInitCmsPageReturnsNullForUnknownUrl()
    {
        $this->assertNull(Controller::instance()->initCmsPage('/missing'));
    }

    public function testGetPageContents()
    {
        $cmsPage = Controller::instance()->initCmsPage('/about');
        $contents = Controller::instance()->getPageContents($cmsPage);

        $this->assertStringContainsString('<p>About content</p>', $contents);
    }

    public function testParseSyntaxFieldsFallsBackOnInvalidContent()
    {
        $content = '{invalid syntax';
        $this->assertEquals($content, Controller::instance()->parseSyntaxFields($content));
    }
}
