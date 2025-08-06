<?php

namespace App\Services;

use App\Models\Project;
use App\Services\AIAnalysisService;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use DOMDocument;
use DOMXPath;

class WebScrapingService
{
    protected Project $project;
    protected array $visitedUrls = [];
    protected array $discoveredUrls = [];
    protected array $screenshots = [];
    protected array $formData = [];
    protected array $apiRequests = [];

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function scrape(): bool
    {
        try {
            $this->project->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Login if credentials are provided
            if ($this->project->login_url && $this->project->username) {
                $this->performLogin();
            }

            // Start scraping from the main URL
            $this->scrapeUrl($this->project->url, 0);

            // Generate AI analysis
            $aiService = new AIAnalysisService($this->project);
            
            // Update project with results
            $this->project->update([
                'status' => 'completed',
                'completed_at' => now(),
                'scraped_urls' => $this->discoveredUrls,
                'screenshots' => $this->screenshots,
                'form_data' => $this->formData,
                'api_requests' => $this->apiRequests,
            ]);

            // Generate AI description and model schema
            $description = $aiService->generateDescription();
            $modelSchema = $aiService->generateModelSchema();
            
            $this->project->update([
                'description' => $description,
                'model_schema' => $modelSchema,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Scraping failed: ' . $e->getMessage());
            
            $this->project->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            return false;
        }
    }

    protected function performLogin(): void
    {
        $browser = Browsershot::url($this->project->login_url)
            ->setNodeBinary($this->getNodeBinary())
            ->setNpmBinary($this->getNpmBinary());

        // Basic login form filling
        $loginData = array_merge([
            'username' => $this->project->username,
            'password' => $this->project->password,
        ], $this->project->login_data ?? []);

        // Save login attempt data
        $this->formData[] = [
            'url' => $this->project->login_url,
            'type' => 'login',
            'data' => $loginData,
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function scrapeUrl(string $url, int $depth): void
    {
        if ($depth > $this->project->max_depth || in_array($url, $this->visitedUrls)) {
            return;
        }

        $this->visitedUrls[] = $url;
        Log::info("Scraping URL: {$url} at depth {$depth}");

        try {
            // Take screenshot
            $screenshotPath = $this->takeScreenshot($url, $depth);
            
            // Get page content
            $html = Browsershot::url($url)
                ->setNodeBinary($this->getNodeBinary())
                ->setNpmBinary($this->getNpmBinary())
                ->bodyHtml();

            // Parse HTML and extract information
            $this->parseHtml($html, $url, $depth);

            // Find and scrape links
            $links = $this->extractLinks($html, $url);
            foreach ($links as $link) {
                if ($this->isValidUrl($link)) {
                    $this->scrapeUrl($link, $depth + 1);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to scrape {$url}: " . $e->getMessage());
        }
    }

    protected function takeScreenshot(string $url, int $depth): string
    {
        $filename = 'screenshots/' . $this->project->id . '/' . 
                   'depth_' . $depth . '_' . md5($url) . '.png';
        
        $screenshotPath = storage_path('app/public/' . $filename);
        
        // Ensure directory exists
        $directory = dirname($screenshotPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        Browsershot::url($url)
            ->setNodeBinary($this->getNodeBinary())
            ->setNpmBinary($this->getNpmBinary())
            ->windowSize(1920, 1080)
            ->save($screenshotPath);

        $this->screenshots[] = [
            'url' => $url,
            'depth' => $depth,
            'path' => $filename,
            'timestamp' => now()->toISOString(),
        ];

        return $filename;
    }

    protected function parseHtml(string $html, string $url, int $depth): void
    {
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);

        // Extract forms
        $forms = $xpath->query('//form');
        foreach ($forms as $form) {
            $this->extractFormData($form, $url);
        }

        // Extract potential API endpoints from JavaScript
        $scripts = $xpath->query('//script');
        foreach ($scripts as $script) {
            $this->extractApiEndpoints($script->textContent, $url);
        }

        // Store URL info
        $this->discoveredUrls[] = [
            'url' => $url,
            'depth' => $depth,
            'title' => $this->extractTitle($doc),
            'meta_description' => $this->extractMetaDescription($xpath),
            'forms_count' => $forms->length,
            'timestamp' => now()->toISOString(),
        ];
    }

    protected function extractFormData($form, string $url): void
    {
        $formData = [
            'url' => $url,
            'action' => $form->getAttribute('action'),
            'method' => $form->getAttribute('method') ?: 'GET',
            'fields' => [],
        ];

        $inputs = $form->getElementsByTagName('input');
        foreach ($inputs as $input) {
            $formData['fields'][] = [
                'name' => $input->getAttribute('name'),
                'type' => $input->getAttribute('type'),
                'required' => $input->hasAttribute('required'),
                'placeholder' => $input->getAttribute('placeholder'),
            ];
        }

        $selects = $form->getElementsByTagName('select');
        foreach ($selects as $select) {
            $options = [];
            foreach ($select->getElementsByTagName('option') as $option) {
                $options[] = [
                    'value' => $option->getAttribute('value'),
                    'text' => $option->textContent,
                ];
            }
            
            $formData['fields'][] = [
                'name' => $select->getAttribute('name'),
                'type' => 'select',
                'required' => $select->hasAttribute('required'),
                'options' => $options,
            ];
        }

        $this->formData[] = $formData;
    }

    protected function extractApiEndpoints(string $scriptContent, string $url): void
    {
        // Simple regex to find API endpoints
        $patterns = [
            '/fetch\s*\(\s*[\'"`]([^\'"`]+)[\'"`]/',
            '/axios\.[get|post|put|delete]+\s*\(\s*[\'"`]([^\'"`]+)[\'"`]/',
            '/\$\.ajax\s*\(\s*{[^}]*url\s*:\s*[\'"`]([^\'"`]+)[\'"`]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $scriptContent, $matches)) {
                foreach ($matches[1] as $endpoint) {
                    $this->apiRequests[] = [
                        'url' => $url,
                        'endpoint' => $endpoint,
                        'found_in' => 'javascript',
                        'timestamp' => now()->toISOString(),
                    ];
                }
            }
        }
    }

    protected function extractLinks(string $html, string $baseUrl): array
    {
        $doc = new DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new DOMXPath($doc);
        
        $links = [];
        $anchors = $xpath->query('//a[@href]');
        
        foreach ($anchors as $anchor) {
            $href = $anchor->getAttribute('href');
            $absoluteUrl = $this->makeAbsoluteUrl($href, $baseUrl);
            if ($absoluteUrl) {
                $links[] = $absoluteUrl;
            }
        }

        return array_unique($links);
    }

    protected function makeAbsoluteUrl(string $url, string $baseUrl): ?string
    {
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        $parsedBase = parse_url($baseUrl);
        if (!$parsedBase) {
            return null;
        }

        $scheme = $parsedBase['scheme'] ?? 'https';
        $host = $parsedBase['host'] ?? '';

        if (str_starts_with($url, '//')) {
            return $scheme . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            return $scheme . '://' . $host . $url;
        }

        return $scheme . '://' . $host . '/' . ltrim($url, '/');
    }

    protected function isValidUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsedUrl = parse_url($url);
        $parsedBaseUrl = parse_url($this->project->url);

        // Only scrape URLs from the same domain
        return ($parsedUrl['host'] ?? '') === ($parsedBaseUrl['host'] ?? '');
    }

    protected function extractTitle(DOMDocument $doc): string
    {
        $titles = $doc->getElementsByTagName('title');
        return $titles->length > 0 ? trim($titles->item(0)->textContent) : '';
    }

    protected function extractMetaDescription(DOMXPath $xpath): string
    {
        $metas = $xpath->query('//meta[@name="description"]/@content');
        return $metas->length > 0 ? trim($metas->item(0)->textContent) : '';
    }

    protected function getNodeBinary(): string
    {
        // Detect operating system
        $os = PHP_OS_FAMILY;
        
        // Try to detect Node.js binary automatically
        $possiblePaths = [];
        
        if ($os === 'Linux') {
            // Debian/Ubuntu specific paths
            $possiblePaths = [
                '/usr/bin/node',               // Standard Debian/Ubuntu location
                '/usr/bin/nodejs',             // Older Debian/Ubuntu (nodejs package)
                '/usr/local/bin/node',         // Manually installed
                '/snap/bin/node',              // Snap package
                '/home/' . get_current_user() . '/.nvm/versions/node/*/bin/node', // NVM
                'node',                        // System PATH
            ];
        } elseif ($os === 'Darwin') {
            // macOS paths
            $possiblePaths = [
                '/opt/homebrew/bin/node',     // macOS Apple Silicon Homebrew
                '/usr/local/bin/node',        // macOS Intel Homebrew
                '/usr/bin/node',               // System
                'node',                        // System PATH
            ];
        } else {
            // Windows or other
            $possiblePaths = ['node'];
        }

        foreach ($possiblePaths as $path) {
            // Handle wildcard paths (for NVM)
            if (strpos($path, '*') !== false) {
                $matches = glob($path);
                if (!empty($matches)) {
                    // Use the latest version
                    sort($matches);
                    $path = end($matches);
                } else {
                    continue;
                }
            }
            
            if ($this->commandExists($path)) {
                Log::info("Node.js binary found at: {$path}");
                return $path;
            }
        }

        // Fallback to system node
        Log::warning("Node.js binary not found in expected locations, using system PATH");
        return 'node';
    }

    protected function getNpmBinary(): string
    {
        // Detect operating system
        $os = PHP_OS_FAMILY;
        
        // Try to detect NPM binary automatically
        $possiblePaths = [];
        
        if ($os === 'Linux') {
            // Debian/Ubuntu specific paths
            $possiblePaths = [
                '/usr/bin/npm',                // Standard Debian/Ubuntu location
                '/usr/local/bin/npm',          // Manually installed
                '/snap/bin/npm',               // Snap package
                '/home/' . get_current_user() . '/.nvm/versions/node/*/bin/npm', // NVM
                'npm',                         // System PATH
            ];
        } elseif ($os === 'Darwin') {
            // macOS paths
            $possiblePaths = [
                '/opt/homebrew/bin/npm',      // macOS Apple Silicon Homebrew
                '/usr/local/bin/npm',         // macOS Intel Homebrew  
                '/usr/bin/npm',                // System
                'npm',                         // System PATH
            ];
        } else {
            // Windows or other
            $possiblePaths = ['npm'];
        }

        foreach ($possiblePaths as $path) {
            // Handle wildcard paths (for NVM)
            if (strpos($path, '*') !== false) {
                $matches = glob($path);
                if (!empty($matches)) {
                    // Use the latest version
                    sort($matches);
                    $path = end($matches);
                } else {
                    continue;
                }
            }
            
            if ($this->commandExists($path)) {
                Log::info("NPM binary found at: {$path}");
                return $path;
            }
        }

        // Fallback to system npm
        Log::warning("NPM binary not found in expected locations, using system PATH");
        return 'npm';
    }

    protected function commandExists(string $command): bool
    {
        if (strpos($command, '/') !== false) {
            // Full path - check if file exists and is executable
            return is_executable($command);
        }

        // Command name - check in PATH
        $result = shell_exec("which $command 2>/dev/null");
        return !empty($result);
    }
}