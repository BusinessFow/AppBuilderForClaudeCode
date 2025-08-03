<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Services\AIAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AIAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_analysis_service_initialization()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $this->assertInstanceOf(AIAnalysisService::class, $service);
    }

    public function test_generate_basic_description()
    {
        $project = Project::factory()->create([
            'name' => 'Test App',
            'url' => 'https://testapp.com',
            'scraped_urls' => [
                ['url' => 'https://testapp.com/home', 'title' => 'Home'],
                ['url' => 'https://testapp.com/about', 'title' => 'About'],
            ],
            'form_data' => [
                ['action' => '/login', 'method' => 'POST', 'fields' => []],
            ],
            'api_requests' => [
                ['endpoint' => '/api/users', 'method' => 'GET'],
            ],
        ]);

        $service = new AIAnalysisService($project);
        $description = $service->generateDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('Test App', $description);
        $this->assertStringContainsString('https://testapp.com', $description);
        $this->assertStringContainsString('2', $description); // Number of URLs
    }

    public function test_generate_basic_schema()
    {
        $project = Project::factory()->create([
            'scraped_urls' => [
                ['url' => 'https://example.com/users/1'],
                ['url' => 'https://example.com/products'],
            ],
            'form_data' => [
                [
                    'action' => '/users',
                    'method' => 'POST',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text'],
                        ['name' => 'email', 'type' => 'email'],
                    ]
                ],
            ],
        ]);

        $service = new AIAnalysisService($project);
        $schema = $service->generateModelSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('models', $schema);
        $this->assertArrayHasKey('relationships', $schema);
        $this->assertArrayHasKey('analysis', $schema);
    }

    public function test_analyze_url_patterns()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeUrlPatterns');
        $method->setAccessible(true);
        
        $urls = [
            ['url' => 'https://example.com/users/1'],
            ['url' => 'https://example.com/users/2'],
            ['url' => 'https://example.com/products/widget'],
            ['url' => 'https://example.com/admin/dashboard'],
        ];
        
        $patterns = $method->invoke($service, $urls);
        
        $this->assertIsArray($patterns);
        $this->assertContains('users', array_keys($patterns));
        $this->assertContains('products', array_keys($patterns));
    }

    public function test_analyze_form_types()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeFormTypes');
        $method->setAccessible(true);
        
        $forms = [
            ['action' => '/login', 'method' => 'POST'],
            ['action' => '/register', 'method' => 'POST'],
            ['action' => '/contact', 'method' => 'POST'],
            ['action' => '/search', 'method' => 'GET'],
        ];
        
        $types = $method->invoke($service, $forms);
        
        $this->assertIsArray($types);
        $this->assertContains('authentication', $types);
        $this->assertContains('registration', $types);
        $this->assertContains('contact', $types);
        $this->assertContains('search', $types);
    }

    public function test_suggest_model_from_form()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('suggestModelFromForm');
        $method->setAccessible(true);
        
        $this->assertEquals('User', $method->invoke($service, ['action' => '/users/create']));
        $this->assertEquals('Product', $method->invoke($service, ['action' => '/products/store']));
        $this->assertEquals('Order', $method->invoke($service, ['action' => '/orders/new']));
        $this->assertEquals('Contact', $method->invoke($service, ['action' => '/contact/send']));
        $this->assertNull($method->invoke($service, ['action' => '/unknown/action']));
    }

    public function test_extract_fields_from_form()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('extractFieldsFromForm');
        $method->setAccessible(true);
        
        $form = [
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'age', 'type' => 'number', 'required' => false],
                ['name' => 'active', 'type' => 'checkbox', 'required' => false],
            ]
        ];
        
        $fields = $method->invoke($service, $form);
        
        $this->assertIsArray($fields);
        $this->assertArrayHasKey('name', $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('age', $fields);
        $this->assertArrayHasKey('active', $fields);
        
        $this->assertEquals('string', $fields['name']['type']);
        $this->assertEquals('string', $fields['email']['type']);
        $this->assertEquals('integer', $fields['age']['type']);
        $this->assertEquals('boolean', $fields['active']['type']);
    }

    public function test_map_html_type_to_db_type()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapHtmlTypeToDbType');
        $method->setAccessible(true);
        
        $this->assertEquals('string', $method->invoke($service, 'email'));
        $this->assertEquals('string', $method->invoke($service, 'password'));
        $this->assertEquals('integer', $method->invoke($service, 'number'));
        $this->assertEquals('string', $method->invoke($service, 'tel'));
        $this->assertEquals('string', $method->invoke($service, 'url'));
        $this->assertEquals('date', $method->invoke($service, 'date'));
        $this->assertEquals('datetime', $method->invoke($service, 'datetime-local'));
        $this->assertEquals('time', $method->invoke($service, 'time'));
        $this->assertEquals('boolean', $method->invoke($service, 'checkbox'));
        $this->assertEquals('string', $method->invoke($service, 'select'));
        $this->assertEquals('text', $method->invoke($service, 'textarea'));
        $this->assertEquals('string', $method->invoke($service, 'unknown'));
    }

    public function test_suggest_basic_relationships()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('suggestBasicRelationships');
        $method->setAccessible(true);
        
        $modelNames = ['User', 'Order', 'Product'];
        $relationships = $method->invoke($service, $modelNames);
        
        $this->assertIsArray($relationships);
        
        // Check for User -> Order relationship
        $userOrderRelation = collect($relationships)->first(function ($rel) {
            return $rel['from'] === 'User' && $rel['to'] === 'Order';
        });
        $this->assertNotNull($userOrderRelation);
        $this->assertEquals('hasMany', $userOrderRelation['type']);
        
        // Check for Order -> Product relationship
        $orderProductRelation = collect($relationships)->first(function ($rel) {
            return $rel['from'] === 'Order' && $rel['to'] === 'Product';
        });
        $this->assertNotNull($orderProductRelation);
        $this->assertEquals('belongsToMany', $orderProductRelation['type']);
    }

    public function test_generate_field_validation_rules()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateFieldValidationRules');
        $method->setAccessible(true);
        
        $field = [
            'name' => 'email',
            'type' => 'string',
            'required' => true,
            'html_type' => 'email'
        ];
        
        $rules = $method->invoke($service, $field);
        
        $this->assertIsArray($rules);
        $this->assertContains('required', $rules);
        $this->assertContains('email', $rules);
        $this->assertContains('string', $rules);
    }

    public function test_analyze_api_requests()
    {
        $project = Project::factory()->create([
            'api_requests' => [
                ['endpoint' => '/api/users', 'method' => 'GET'],
                ['endpoint' => '/api/users', 'method' => 'POST'],
                ['endpoint' => '/api/users/1', 'method' => 'GET'],
                ['endpoint' => '/api/users/1', 'method' => 'PUT'],
                ['endpoint' => '/api/users/1', 'method' => 'DELETE'],
                ['endpoint' => '/api/products', 'method' => 'GET'],
                ['endpoint' => '/api/orders', 'method' => 'POST'],
            ]
        ]);
        
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('analyzeApiRequests');
        $method->setAccessible(true);
        
        $analysis = $method->invoke($service, $project->api_requests);
        
        $this->assertIsArray($analysis);
        $this->assertArrayHasKey('resources', $analysis);
        $this->assertArrayHasKey('restful_resources', $analysis);
        
        $this->assertContains('users', $analysis['resources']);
        $this->assertContains('products', $analysis['resources']);
        $this->assertContains('orders', $analysis['resources']);
        
        // Users should be identified as a RESTful resource
        $this->assertContains('users', $analysis['restful_resources']);
    }

    public function test_generate_description_with_complex_data()
    {
        $project = Project::factory()->create([
            'name' => 'Complex App',
            'url' => 'https://complex-app.com',
            'scraped_urls' => [
                ['url' => 'https://complex-app.com/home', 'title' => 'Home Page'],
                ['url' => 'https://complex-app.com/users', 'title' => 'Users List'],
                ['url' => 'https://complex-app.com/products', 'title' => 'Products'],
                ['url' => 'https://complex-app.com/orders', 'title' => 'Orders'],
                ['url' => 'https://complex-app.com/admin', 'title' => 'Admin Panel'],
            ],
            'form_data' => [
                ['action' => '/login', 'method' => 'POST', 'fields' => [
                    ['name' => 'email', 'type' => 'email'],
                    ['name' => 'password', 'type' => 'password'],
                ]],
                ['action' => '/register', 'method' => 'POST', 'fields' => [
                    ['name' => 'name', 'type' => 'text'],
                    ['name' => 'email', 'type' => 'email'],
                    ['name' => 'password', 'type' => 'password'],
                ]],
                ['action' => '/products/create', 'method' => 'POST', 'fields' => [
                    ['name' => 'name', 'type' => 'text'],
                    ['name' => 'price', 'type' => 'number'],
                    ['name' => 'description', 'type' => 'textarea'],
                ]],
            ],
            'api_requests' => [
                ['endpoint' => '/api/users', 'method' => 'GET'],
                ['endpoint' => '/api/products', 'method' => 'GET'],
                ['endpoint' => '/api/orders', 'method' => 'GET'],
                ['endpoint' => '/api/stats', 'method' => 'GET'],
            ],
        ]);

        $service = new AIAnalysisService($project);
        $description = $service->generateDescription();

        $this->assertIsString($description);
        $this->assertStringContainsString('Complex App', $description);
        $this->assertStringContainsString('5', $description); // Number of URLs
        $this->assertStringContainsString('3', $description); // Number of forms
        $this->assertStringContainsString('4', $description); // Number of API endpoints
        $this->assertStringContainsString('authentication', strtolower($description));
        $this->assertStringContainsString('products', strtolower($description));
    }

    public function test_detect_crud_operations_from_forms()
    {
        $project = Project::factory()->create();
        $service = new AIAnalysisService($project);
        
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('detectCrudOperations');
        $method->setAccessible(true);
        
        $forms = [
            ['action' => '/users/create', 'method' => 'GET'],
            ['action' => '/users', 'method' => 'POST'],
            ['action' => '/users/1/edit', 'method' => 'GET'],
            ['action' => '/users/1', 'method' => 'PUT'],
            ['action' => '/users/1', 'method' => 'DELETE'],
        ];
        
        $operations = $method->invoke($service, $forms);
        
        $this->assertIsArray($operations);
        $this->assertArrayHasKey('User', $operations);
        
        $userOps = $operations['User'];
        $this->assertContains('create', $userOps);
        $this->assertContains('update', $userOps);
        $this->assertContains('delete', $userOps);
    }

    public function test_generate_comprehensive_model_schema()
    {
        $project = Project::factory()->create([
            'scraped_urls' => [
                ['url' => 'https://example.com/users'],
                ['url' => 'https://example.com/users/1'],
                ['url' => 'https://example.com/products'],
                ['url' => 'https://example.com/orders'],
                ['url' => 'https://example.com/categories'],
            ],
            'form_data' => [
                [
                    'action' => '/users',
                    'method' => 'POST',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'email', 'type' => 'email', 'required' => true],
                        ['name' => 'role_id', 'type' => 'select', 'required' => true],
                    ]
                ],
                [
                    'action' => '/products',
                    'method' => 'POST',
                    'fields' => [
                        ['name' => 'name', 'type' => 'text', 'required' => true],
                        ['name' => 'price', 'type' => 'number', 'required' => true],
                        ['name' => 'category_id', 'type' => 'select', 'required' => true],
                        ['name' => 'in_stock', 'type' => 'checkbox'],
                    ]
                ],
            ],
            'api_requests' => [
                ['endpoint' => '/api/users', 'method' => 'GET'],
                ['endpoint' => '/api/roles', 'method' => 'GET'],
                ['endpoint' => '/api/products', 'method' => 'GET'],
                ['endpoint' => '/api/categories', 'method' => 'GET'],
            ],
        ]);

        $service = new AIAnalysisService($project);
        $schema = $service->generateModelSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('models', $schema);
        $this->assertArrayHasKey('relationships', $schema);
        
        // Check models
        $models = $schema['models'];
        $this->assertArrayHasKey('User', $models);
        $this->assertArrayHasKey('Product', $models);
        
        // Check User fields
        $userFields = $models['User']['fields'];
        $this->assertArrayHasKey('name', $userFields);
        $this->assertArrayHasKey('email', $userFields);
        $this->assertArrayHasKey('role_id', $userFields);
        
        // Check Product fields
        $productFields = $models['Product']['fields'];
        $this->assertArrayHasKey('name', $productFields);
        $this->assertArrayHasKey('price', $productFields);
        $this->assertArrayHasKey('category_id', $productFields);
        $this->assertArrayHasKey('in_stock', $productFields);
        
        // Check field types
        $this->assertEquals('string', $userFields['name']['type']);
        $this->assertEquals('string', $userFields['email']['type']);
        $this->assertEquals('integer', $productFields['price']['type']);
        $this->assertEquals('boolean', $productFields['in_stock']['type']);
        
        // Check relationships
        $relationships = $schema['relationships'];
        $this->assertNotEmpty($relationships);
        
        // Should detect User->Role relationship
        $userRoleRelation = collect($relationships)->first(function ($rel) {
            return $rel['from'] === 'User' && $rel['to'] === 'Role';
        });
        $this->assertNotNull($userRoleRelation);
        
        // Should detect Product->Category relationship
        $productCategoryRelation = collect($relationships)->first(function ($rel) {
            return $rel['from'] === 'Product' && $rel['to'] === 'Category';
        });
        $this->assertNotNull($productCategoryRelation);
    }
}