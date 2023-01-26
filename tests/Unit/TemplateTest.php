<?php

declare(strict_types=1);

namespace CrowdSecBouncer\Tests\Unit;

/**
 * Test for templating.
 *
 * @author    CrowdSec team
 *
 * @see      https://crowdsec.net CrowdSec Official Website
 *
 * @copyright Copyright (c) 2022+ CrowdSec
 * @license   MIT License
 */

use PHPUnit\Framework\TestCase;
use CrowdSecBouncer\Template;

/**
 * @covers CrowdSecBouncer\Template::__construct
 * @covers CrowdSecBouncer\Template::render
 *
 */
final class TemplateTest extends TestCase
{
    public function testRender()
    {
        $template = new Template('ban.html.twig', __DIR__ . "/../../src/templates");
        $render = $template->render(
            [
                'text' => [
                    'ban_wall' => [
                        'title' => 'BAN TEST TITLE'
                    ]
                ]
            ]
        );

        $this->assertStringContainsString('<h1>BAN TEST TITLE</h1>', $render, 'Ban rendering should be as expected');
        $this->assertStringNotContainsString('<p class="footer">', $render, 'Ban rendering should not contain footer');

        $render = $template->render(
            [
                'text' => [
                    'ban_wall' => [
                        'title' => 'BAN TEST TITLE',
                        'footer' => 'This is a footer test'
                    ]
                ]
            ]
        );

        $this->assertStringContainsString('<p class="footer">This is a footer test</p>', $render, 'Ban rendering should contain footer');


        $template = new Template('captcha.html.twig', __DIR__ . "/../../src/templates");
        $render = $template->render(
            [
                'text' => [
                    'captcha_wall' => [
                        'title' => 'CAPTCHA TEST TITLE'
                    ]
                ]
            ]
        );

        $this->assertStringContainsString('CAPTCHA TEST TITLE', $render, 'Captcha rendering should be as expected');
        $this->assertStringContainsString('<form method="post" id="captcha" action="#">', $render, 'Captcha rendering should be as expected');
    }
}
