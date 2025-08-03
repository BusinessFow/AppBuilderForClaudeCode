<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    protected Project $project;

    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    public function generateDescription(): string
    {
        $scrapingData = $this->prepareScrappingDataForAI();
        
        $prompt = $this->buildDescriptionPrompt($scrapingData);
        
        // In a real implementation, you would call an AI service like OpenAI
        // For now, we'll generate a basic description based on the scraped data
        return $this->generateBasicDescription($scrapingData);
    }

    public function generateModelSchema(): array
    {
        $scrapingData = $this->prepareScrappingDataForAI();
        
        $prompt = $this->buildSchemaPrompt($scrapingData);
        
        // In a real implementation, you would call an AI service like OpenAI
        // For now, we'll generate a basic schema based on the scraped data
        return $this->generateBasicSchema($scrapingData);
    }

    protected function prepareScrappingDataForAI(): array
    {
        return [
            'project' => [
                'name' => $this->project->name,
                'url' => $this->project->url,
                'total_urls' => count($this->project->scraped_urls ?? []),
                'total_forms' => count($this->project->form_data ?? []),
                'total_api_requests' => count($this->project->api_requests ?? []),
            ],
            'scraped_urls' => array_slice($this->project->scraped_urls ?? [], 0, 10), // Limit for AI processing
            'forms' => array_slice($this->project->form_data ?? [], 0, 5),
            'api_requests' => array_slice($this->project->api_requests ?? [], 0, 10),
            'screenshots' => count($this->project->screenshots ?? []),
        ];
    }

    protected function buildDescriptionPrompt(array $data): string
    {
        return "Based on the following web scraping data, generate a comprehensive description of the web application:\n\n" .
               "Project: {$data['project']['name']}\n" .
               "URL: {$data['project']['url']}\n" .
               "Total URLs found: {$data['project']['total_urls']}\n" .
               "Total forms found: {$data['project']['total_forms']}\n" .
               "Total API endpoints found: {$data['project']['total_api_requests']}\n\n" .
               "Sample URLs: " . json_encode(array_slice($data['scraped_urls'], 0, 5)) . "\n" .
               "Sample forms: " . json_encode(array_slice($data['forms'], 0, 3)) . "\n" .
               "Sample API endpoints: " . json_encode(array_slice($data['api_requests'], 0, 5)) . "\n\n" .
               "Please provide a detailed description of what this web application does, its main features, and its purpose.";
    }

    protected function buildSchemaPrompt(array $data): string
    {
        return "Based on the following web scraping data, generate a database schema with models, fields, and relationships:\n\n" .
               json_encode($data, JSON_PRETTY_PRINT) . "\n\n" .
               "Please provide a JSON schema with models, their fields (with types), and relationships between models. " .
               "Base the schema on the forms, API endpoints, and URL patterns found during scraping.";
    }

    protected function generateBasicDescription(array $data): string
    {
        $description = "This web application '{$data['project']['name']}' is hosted at {$data['project']['url']}. ";
        
        if ($data['project']['total_urls'] > 0) {
            $description .= "The application consists of {$data['project']['total_urls']} discoverable pages. ";
        }
        
        if ($data['project']['total_forms'] > 0) {
            $description .= "It contains {$data['project']['total_forms']} forms for user interaction. ";
        }
        
        if ($data['project']['total_api_requests'] > 0) {
            $description .= "The application uses {$data['project']['total_api_requests']} API endpoints for data operations. ";
        }

        // Analyze URL patterns
        $urlPatterns = $this->analyzeUrlPatterns($data['scraped_urls']);
        if (!empty($urlPatterns)) {
            $description .= "Common URL patterns suggest the following sections: " . implode(', ', $urlPatterns) . ". ";
        }

        // Analyze forms
        $formTypes = $this->analyzeFormTypes($data['forms']);
        if (!empty($formTypes)) {
            $description .= "The application includes these types of forms: " . implode(', ', $formTypes) . ". ";
        }

        return $description;
    }

    protected function generateBasicSchema(array $data): array
    {
        $schema = [
            'models' => [],
            'relationships' => [],
            'analysis' => [
                'confidence' => 'low',
                'method' => 'basic_pattern_analysis',
                'timestamp' => now()->toISOString(),
            ]
        ];

        // Analyze forms to suggest models
        foreach ($data['forms'] as $form) {
            $modelName = $this->suggestModelFromForm($form);
            if ($modelName && !isset($schema['models'][$modelName])) {
                $schema['models'][$modelName] = [
                    'fields' => $this->extractFieldsFromForm($form),
                    'source' => 'form_analysis',
                ];
            }
        }

        // Analyze URL patterns to suggest additional models
        $urlModels = $this->suggestModelsFromUrls($data['scraped_urls']);
        foreach ($urlModels as $modelName => $info) {
            if (!isset($schema['models'][$modelName])) {
                $schema['models'][$modelName] = [
                    'fields' => $info['fields'],
                    'source' => 'url_pattern_analysis',
                ];
            }
        }

        // Suggest basic relationships
        $schema['relationships'] = $this->suggestBasicRelationships(array_keys($schema['models']));

        return $schema;
    }

    protected function analyzeUrlPatterns(array $urls): array
    {
        $patterns = [];
        
        foreach ($urls as $urlData) {
            $path = parse_url($urlData['url'], PHP_URL_PATH);
            $segments = array_filter(explode('/', $path));
            
            foreach ($segments as $segment) {
                if (strlen($segment) > 2 && !is_numeric($segment)) {
                    $patterns[] = $segment;
                }
            }
        }

        return array_unique(array_slice(array_count_values($patterns), 0, 5));
    }

    protected function analyzeFormTypes(array $forms): array
    {
        $types = [];
        
        foreach ($forms as $form) {
            if (stripos($form['action'], 'login') !== false) {
                $types[] = 'authentication';
            } elseif (stripos($form['action'], 'register') !== false) {
                $types[] = 'registration';
            } elseif (stripos($form['action'], 'contact') !== false) {
                $types[] = 'contact';
            } elseif (stripos($form['action'], 'search') !== false) {
                $types[] = 'search';
            } else {
                $types[] = 'data_entry';
            }
        }

        return array_unique($types);
    }

    protected function suggestModelFromForm(array $form): ?string
    {
        $action = strtolower($form['action']);
        
        if (stripos($action, 'user') !== false || stripos($action, 'login') !== false) {
            return 'User';
        }
        if (stripos($action, 'product') !== false) {
            return 'Product';
        }
        if (stripos($action, 'order') !== false) {
            return 'Order';
        }
        if (stripos($action, 'contact') !== false) {
            return 'Contact';
        }
        
        return null;
    }

    protected function extractFieldsFromForm(array $form): array
    {
        $fields = [];
        
        foreach ($form['fields'] as $field) {
            if (empty($field['name'])) continue;
            
            $fieldType = $this->mapHtmlTypeToDbType($field['type']);
            $fields[$field['name']] = [
                'type' => $fieldType,
                'required' => $field['required'] ?? false,
                'html_type' => $field['type'],
            ];
        }

        return $fields;
    }

    protected function suggestModelsFromUrls(array $urls): array
    {
        $models = [];
        
        foreach ($urls as $urlData) {
            $path = parse_url($urlData['url'], PHP_URL_PATH);
            $segments = array_filter(explode('/', $path));
            
            foreach ($segments as $segment) {
                if (strlen($segment) > 3 && !is_numeric($segment) && !in_array($segment, ['admin', 'api', 'public', 'assets'])) {
                    $modelName = ucfirst(rtrim($segment, 's')); // Singularize
                    if (!isset($models[$modelName])) {
                        $models[$modelName] = [
                            'fields' => [
                                'id' => ['type' => 'integer', 'primary' => true],
                                'name' => ['type' => 'string'],
                                'created_at' => ['type' => 'timestamp'],
                                'updated_at' => ['type' => 'timestamp'],
                            ]
                        ];
                    }
                }
            }
        }

        return $models;
    }

    protected function suggestBasicRelationships(array $modelNames): array
    {
        $relationships = [];
        
        // Basic relationship suggestions based on common patterns
        if (in_array('User', $modelNames) && in_array('Order', $modelNames)) {
            $relationships[] = [
                'from' => 'User',
                'to' => 'Order',
                'type' => 'hasMany',
                'inverse' => 'belongsTo'
            ];
        }
        
        if (in_array('Order', $modelNames) && in_array('Product', $modelNames)) {
            $relationships[] = [
                'from' => 'Order',
                'to' => 'Product',
                'type' => 'belongsToMany',
                'inverse' => 'belongsToMany'
            ];
        }

        return $relationships;
    }

    protected function mapHtmlTypeToDbType(string $htmlType): string
    {
        return match($htmlType) {
            'email' => 'string',
            'password' => 'string',
            'number' => 'integer',
            'tel' => 'string',
            'url' => 'string',
            'date' => 'date',
            'datetime-local' => 'datetime',
            'time' => 'time',
            'checkbox' => 'boolean',
            'select' => 'string',
            'textarea' => 'text',
            default => 'string',
        };
    }

    /**
     * Call external AI service (like OpenAI) for advanced analysis
     * This is a placeholder for real AI integration
     */
    protected function callAIService(string $prompt): ?string
    {
        // Example implementation with OpenAI
        // Uncomment and configure when you have an API key
        /*
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('openai.api_key'),
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 1000,
            ]);

            return $response->json('choices.0.message.content');
        } catch (\Exception $e) {
            Log::error('AI service call failed: ' . $e->getMessage());
            return null;
        }
        */
        
        return null;
    }
}