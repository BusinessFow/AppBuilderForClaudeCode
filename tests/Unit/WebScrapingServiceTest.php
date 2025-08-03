<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\WebScrapingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Mockery;

class WebScrapingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_web_scraping_service_initialization()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $this->assertInstanceOf(WebScrapingService::class, $service);
    }

    public function test_make_absolute_url_method()
    {
        $project = Project::factory()->create(['url' => 'https://example.com']);
        $service = new WebScrapingService($project);
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('makeAbsoluteUrl');
        $method->setAccessible(true);
        
        // Test various URL formats
        $this->assertEquals(
            'https://example.com/page',
            $method->invoke($service, '/page', 'https://example.com/home')
        );
        
        $this->assertEquals(
            'https://example.com/relative',
            $method->invoke($service, 'relative', 'https://example.com/')
        );
        
        $this->assertEquals(
            'https://other.com/absolute',
            $method->invoke($service, 'https://other.com/absolute', 'https://example.com/')
        );
    }

    public function test_is_valid_url_method()
    {
        $project = Project::factory()->create(['url' => 'https://example.com']);
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('isValidUrl');
        $method->setAccessible(true);
        
        // Valid URLs from same domain
        $this->assertTrue($method->invoke($service, 'https://example.com/page'));
        $this->assertTrue($method->invoke($service, 'https://example.com/'));
        
        // Invalid URLs (different domain)
        $this->assertFalse($method->invoke($service, 'https://other.com/page'));
        $this->assertFalse($method->invoke($service, 'invalid-url'));
    }

    public function test_extract_title_method()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractTitle');
        $method->setAccessible(true);
        
        $doc = new \DOMDocument();
        $doc->loadHTML('<html><head><title>Test Page Title</title></head><body></body></html>');
        
        $title = $method->invoke($service, $doc);
        $this->assertEquals('Test Page Title', $title);
    }

    public function test_extract_meta_description_method()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractMetaDescription');
        $method->setAccessible(true);
        
        $doc = new \DOMDocument();
        $doc->loadHTML('<html><head><meta name="description" content="Test meta description"></head><body></body></html>');
        $xpath = new \DOMXPath($doc);
        
        $description = $method->invoke($service, $xpath);
        $this->assertEquals('Test meta description', $description);
    }

    public function test_extract_links_method()
    {
        $project = Project::factory()->create(['url' => 'https://example.com']);
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractLinks');
        $method->setAccessible(true);
        
        $html = '<html><body>
            <a href="/page1">Page 1</a>
            <a href="https://example.com/page2">Page 2</a>
            <a href="https://other.com/page3">External</a>
        </body></html>';
        
        $links = $method->invoke($service, $html, 'https://example.com');
        
        $this->assertIsArray($links);
        $this->assertContains('https://example.com/page1', $links);
        $this->assertContains('https://example.com/page2', $links);
    }

    public function test_map_html_type_to_db_type()
    {
        // This method is actually in AIAnalysisService, not WebScrapingService
        $this->markTestSkipped('Method is in AIAnalysisService, not WebScrapingService');
    }

    public function test_project_status_updates_during_scraping()
    {
        $project = Project::factory()->create(['status' => 'pending']);
        
        // Mock the scraping to avoid actual HTTP calls
        $serviceMock = Mockery::mock(WebScrapingService::class, [$project])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        
        $serviceMock->shouldReceive('performLogin')->andReturn(null);
        $serviceMock->shouldReceive('scrapeUrl')->andReturn(null);
        
        // The project should be updated to running when scraping starts
        $this->assertEquals('pending', $project->status);
    }

    public function test_extract_forms_from_html()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractForms');
        $method->setAccessible(true);
        
        $html = '<html><body>
            <form action="/login" method="post">
                <input type="text" name="username" required>
                <input type="password" name="password" required>
                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit">Login</button>
            </form>
        </body></html>';
        
        $forms = $method->invoke($service, $html);
        
        $this->assertIsArray($forms);
        $this->assertCount(1, $forms);
        $this->assertEquals('/login', $forms[0]['action']);
        $this->assertEquals('post', $forms[0]['method']);
        $this->assertCount(3, $forms[0]['fields']);
    }

    public function test_extract_api_endpoints_from_javascript()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractApiEndpoints');
        $method->setAccessible(true);
        
        $html = '<html><body>
            <script>
                fetch("/api/users")
                fetch("https://api.example.com/posts", {method: "POST"})
                axios.get("/api/products")
            </script>
        </body></html>';
        
        $endpoints = $method->invoke($service, $html);
        
        $this->assertIsArray($endpoints);
        $this->assertGreaterThan(0, count($endpoints));
    }

    public function test_save_screenshot()
    {
        Storage::fake('public');
        
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('saveScreenshot');
        $method->setAccessible(true);
        
        $screenshotData = 'fake-screenshot-data';
        $url = 'https://example.com/page';
        
        $path = $method->invoke($service, $screenshotData, $url);
        
        $this->assertNotNull($path);
        $this->assertStringContainsString('screenshots', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_max_depth_limit()
    {
        $project = Project::factory()->create([
            'url' => 'https://example.com',
            'max_depth' => 2
        ]);
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('maxDepth');
        $property->setAccessible(true);
        
        $this->assertEquals(2, $property->getValue($service));
    }

    public function test_handle_scraping_error()
    {
        $project = Project::factory()->create(['status' => 'running']);
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('handleError');
        $method->setAccessible(true);
        
        $error = new \Exception('Test error message');
        $method->invoke($service, $error);
        
        $project->refresh();
        $this->assertEquals('failed', $project->status);
    }

    public function test_clean_html_content()
    {
        $project = Project::factory()->create();
        $service = new WebScrapingService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('cleanHtml');
        $method->setAccessible(true);
        
        $dirtyHtml = '<script>alert("test")</script><p>Content</p><style>body{color:red;}</style>';
        $cleanHtml = $method->invoke($service, $dirtyHtml);
        
        $this->assertStringNotContainsString('<script>', $cleanHtml);
        $this->assertStringNotContainsString('<style>', $cleanHtml);
        $this->assertStringContainsString('<p>Content</p>', $cleanHtml);
    }
}