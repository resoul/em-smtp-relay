<?php

declare(strict_types=1);

namespace Emercury\Smtp\Tests\Unit\Admin;

use Emercury\Smtp\Admin\SettingsPage;
use Emercury\Smtp\Admin\Tabs\GeneralTab;
use Emercury\Smtp\Admin\Tabs\AdvancedTab;
use Emercury\Smtp\Admin\Tabs\TestEmailTab;
use Emercury\Smtp\Admin\Tabs\ConfigManagerTab;
use Emercury\Smtp\Tests\TestCase;
use Brain\Monkey\Functions;
use Mockery;

class SettingsPageTest extends TestCase
{
    private GeneralTab $generalTab;
    private AdvancedTab $advancedTab;
    private TestEmailTab $testEmailTab;
    private ConfigManagerTab $configManagerTab;
    private SettingsPage $settingsPage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generalTab = Mockery::mock(GeneralTab::class);
        $this->advancedTab = Mockery::mock(AdvancedTab::class);
        $this->testEmailTab = Mockery::mock(TestEmailTab::class);
        $this->configManagerTab = Mockery::mock(ConfigManagerTab::class);

        $this->settingsPage = new SettingsPage(
            $this->generalTab,
            $this->advancedTab,
            $this->testEmailTab,
            $this->configManagerTab
        );

        Functions\when('__')->returnArg();
        Functions\when('esc_html__')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
    }

    // registerMenu() tests
    public function testRegisterMenuAddsOptionsPage(): void
    {
        Functions\expect('add_options_page')
            ->once()
            ->with(
                'Emercury SMTP',
                'Emercury SMTP',
                'manage_options',
                'em-smtp-relay-settings',
                [$this->settingsPage, 'render']
            );

        $this->settingsPage->registerMenu();
    }

    public function testRegisterMenuUsesTranslationFunctions(): void
    {
        Functions\expect('__')
            ->twice()
            ->with('Emercury SMTP', 'em-smtp-relay')
            ->andReturn('Emercury SMTP');

        Functions\expect('add_options_page')
            ->once()
            ->andReturn('hook-suffix');

        $this->settingsPage->registerMenu();
    }

    // addActionLinks() tests
    public function testAddActionLinksPrependsSettingsLink(): void
    {
        Functions\expect('admin_url')
            ->once()
            ->with('options-general.php?page=em-smtp-relay-settings')
            ->andReturn('http://example.com/wp-admin/options-general.php?page=em-smtp-relay-settings');

        Functions\expect('esc_url')
            ->once()
            ->andReturnFirstArg();

        Functions\expect('esc_html__')
            ->once()
            ->with('Settings', 'em-smtp-relay')
            ->andReturn('Settings');

        $originalLinks = ['link1' => 'Link 1', 'link2' => 'Link 2'];
        $result = $this->settingsPage->addActionLinks($originalLinks);

        $this->assertCount(3, $result);
        $this->assertStringContainsString('Settings', $result[0]);
        $this->assertStringContainsString('options-general.php?page=em-smtp-relay-settings', $result[0]);
        $this->assertEquals('Link 1', $result['link1']);
        $this->assertEquals('Link 2', $result['link2']);
    }

    public function testAddActionLinksReturnsCorrectHtmlStructure(): void
    {
        Functions\expect('admin_url')
            ->once()
            ->andReturn('http://example.com/admin/settings');

        Functions\expect('esc_url')
            ->once()
            ->with('http://example.com/admin/settings')
            ->andReturn('http://example.com/admin/settings');

        Functions\expect('esc_html__')
            ->once()
            ->andReturn('Settings');

        $result = $this->settingsPage->addActionLinks([]);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('<a href="', $result[0]);
        $this->assertStringContainsString('</a>', $result[0]);
    }

    public function testAddActionLinksPreservesExistingLinks(): void
    {
        Functions\expect('admin_url')->once()->andReturn('url');
        Functions\expect('esc_url')->once()->andReturn('url');
        Functions\expect('esc_html__')->once()->andReturn('Settings');

        $existingLinks = [
            'deactivate' => '<a href="#">Deactivate</a>',
            'delete' => '<a href="#">Delete</a>',
        ];

        $result = $this->settingsPage->addActionLinks($existingLinks);

        $this->assertCount(3, $result);
        $this->assertEquals('<a href="#">Deactivate</a>', $result['deactivate']);
        $this->assertEquals('<a href="#">Delete</a>', $result['delete']);
    }

    public function testAddActionLinksWithEmptyArray(): void
    {
        Functions\expect('admin_url')->once()->andReturn('url');
        Functions\expect('esc_url')->once()->andReturn('url');
        Functions\expect('esc_html__')->once()->andReturn('Settings');

        $result = $this->settingsPage->addActionLinks([]);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Settings', $result[0]);
    }

    // render() tests
    public function testRenderDiesWhenUserLacksPermissions(): void
    {
        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(false);

        Functions\expect('esc_html__')
            ->twice()
            ->andReturnUsing(function ($text) {
                return $text;
            });

        Functions\expect('wp_die')
            ->once()
            ->with(
                'You do not have sufficient permissions to access this page.',
                'Permission Denied',
                ['response' => 403]
            );

        $this->settingsPage->render();
    }

    public function testRenderOutputsWrapDivWhenUserHasPermissions(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')
            ->once()
            ->with('manage_options')
            ->andReturn(true);

        Functions\expect('esc_html__')
            ->once()
            ->with('Emercury SMTP Settings', 'em-smtp-relay')
            ->andReturn('Emercury SMTP Settings');

        Functions\expect('__')
            ->once()
            ->andReturn('Log into your <a href="%s" target="_blank" rel="noopener">Emercury SMTP account</a> to obtain the settings.');

        Functions\expect('wp_kses_post')
            ->once()
            ->andReturnFirstArg();

        Functions\expect('add_query_arg')
            ->times(4)
            ->andReturn('url');

        Functions\expect('admin_url')
            ->times(4)
            ->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('<div class="wrap">', $output);
        $this->assertStringContainsString('</div>', $output);
        $this->assertStringContainsString('Emercury SMTP Settings', $output);
    }

    public function testRenderOutputsPageTitle(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')
            ->once()
            ->with('Emercury SMTP Settings', 'em-smtp-relay')
            ->andReturn('Emercury SMTP Settings');

        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('<h1>', $output);
        $this->assertStringContainsString('Emercury SMTP Settings', $output);
    }

    public function testRenderOutputsDescriptionWithLink(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->once()->andReturn('Title');

        Functions\expect('__')
            ->once()
            ->with(
                'Log into your <a href="%s" target="_blank" rel="noopener">Emercury SMTP account</a> to obtain the settings.',
                'em-smtp-relay'
            )
            ->andReturn('Log into your <a href="%s" target="_blank" rel="noopener">Emercury SMTP account</a> to obtain the settings.');

        Functions\expect('wp_kses_post')
            ->once()
            ->andReturnFirstArg();

        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('https://panel.smtp.emercury.net/', $output);
        $this->assertStringContainsString('target="_blank"', $output);
        $this->assertStringContainsString('rel="noopener"', $output);
    }

    public function testRenderOutputsNavigationTabs(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();

        Functions\expect('add_query_arg')
            ->times(4)
            ->andReturn('url');

        Functions\expect('admin_url')
            ->times(4)
            ->with('options-general.php')
            ->andReturn('http://example.com/wp-admin/options-general.php');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nav-tab-wrapper', $output);
        $this->assertStringContainsString('Settings', $output);
        $this->assertStringContainsString('Test Email', $output);
        $this->assertStringContainsString('Advanced', $output);
        $this->assertStringContainsString('Import/Export', $output);
    }

    public function testRenderMarksCurrentTabAsActive(): void
    {
        $_GET['tab'] = 'advanced';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->advancedTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('nav-tab-active', $output);
    }

    // getCurrentTab() and renderActiveTab() integration tests
    public function testRenderRendersGeneralTabByDefault(): void
    {
        unset($_GET['tab']);

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderRendersGeneralTabWhenTabIsGeneral(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderRendersTestEmailTabWhenTabIsTestEmail(): void
    {
        $_GET['tab'] = 'test-email';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->testEmailTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderRendersAdvancedTabWhenTabIsAdvanced(): void
    {
        $_GET['tab'] = 'advanced';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->advancedTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderRendersConfigManagerTabWhenTabIsConfigManager(): void
    {
        $_GET['tab'] = 'config-manager';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->configManagerTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderRendersGeneralTabForInvalidTabValue(): void
    {
        $_GET['tab'] = 'invalid-tab';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderSanitizesTabParameter(): void
    {
        $_GET['tab'] = '<script>alert("xss")</script>';

        Functions\expect('sanitize_text_field')
            ->once()
            ->with('<script>alert("xss")</script>')
            ->andReturn('scriptalertxssscript');

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    // Tab rendering tests
    public function testRenderTabsOutputsCorrectStructure(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();

        Functions\expect('add_query_arg')
            ->times(4)
            ->withArgs(function ($args, $url) {
                return isset($args['page']) && isset($args['tab']);
            })
            ->andReturn('url');

        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('<h2 class="nav-tab-wrapper">', $output);
        $this->assertStringContainsString('</h2>', $output);
        $this->assertStringContainsString('class="nav-tab', $output);
    }

    public function testRenderTabsIncludesAllFourTabs(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);

        Functions\expect('esc_html__')
            ->times(5)
            ->andReturnUsing(function ($text) {
                return $text;
            });

        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        $this->assertStringContainsString('Settings', $output);
        $this->assertStringContainsString('Test Email', $output);
        $this->assertStringContainsString('Advanced', $output);
        $this->assertStringContainsString('Import/Export', $output);
    }

    public function testRenderTabsBuildsCorrectUrls(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();

        Functions\expect('add_query_arg')
            ->times(4)
            ->withArgs(function ($args) {
                return $args['page'] === 'em-smtp-relay-settings' &&
                    in_array($args['tab'], ['general', 'test-email', 'advanced', 'config-manager']);
            })
            ->andReturn('url');

        Functions\expect('admin_url')
            ->times(4)
            ->with('options-general.php')
            ->andReturn('url');

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderTabsEscapesUrlsAndAttributes(): void
    {
        $_GET['tab'] = 'general';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        Functions\expect('esc_url')
            ->times(4)
            ->andReturnFirstArg();

        Functions\expect('esc_attr')
            ->times(4)
            ->andReturnFirstArg();

        Functions\expect('esc_html')
            ->times(4)
            ->andReturnFirstArg();

        $this->generalTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        ob_get_clean();
    }

    public function testRenderOnlyActiveTabIsMarkedActive(): void
    {
        $_GET['tab'] = 'test-email';

        Functions\expect('current_user_can')->once()->andReturn(true);
        Functions\expect('esc_html__')->times(5)->andReturnFirstArg();
        Functions\expect('__')->once()->andReturn('Description');
        Functions\expect('wp_kses_post')->once()->andReturnFirstArg();
        Functions\expect('add_query_arg')->times(4)->andReturn('url');
        Functions\expect('admin_url')->times(4)->andReturn('url');

        $this->testEmailTab->shouldReceive('render')->once();

        ob_start();
        $this->settingsPage->render();
        $output = ob_get_clean();

        // Should contain nav-tab-active only once (for the active tab)
        $this->assertEquals(1, substr_count($output, 'nav-tab-active'));
    }

    protected function tearDown(): void
    {
        unset($_GET['tab']);
        parent::tearDown();
    }
}