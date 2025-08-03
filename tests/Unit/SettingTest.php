<?php

namespace Tests\Unit;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_setting(): void
    {
        $setting = Setting::create([
            'key' => 'test_key',
            'value' => 'test_value',
            'type' => 'string',
            'description' => 'Test setting',
            'group' => 'general',
            'is_public' => false,
        ]);

        $this->assertDatabaseHas('settings', [
            'key' => 'test_key',
            'value' => 'test_value',
        ]);
    }

    public function test_can_get_setting_with_default(): void
    {
        $value = Setting::get('non_existent_key', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function test_can_set_and_get_setting(): void
    {
        Setting::set('test_key', 'test_value');
        $value = Setting::get('test_key');
        
        $this->assertEquals('test_value', $value);
    }

    public function test_setting_values_are_cached(): void
    {
        Setting::set('cached_key', 'cached_value');
        
        // First call should cache the value
        $value1 = Setting::get('cached_key');
        
        // Update the database directly (bypassing the set method)
        Setting::where('key', 'cached_key')->update(['value' => 'new_value']);
        
        // Should still get cached value
        $value2 = Setting::get('cached_key');
        $this->assertEquals('cached_value', $value2);
        
        // Clear cache and get fresh value
        Cache::forget('setting.cached_key');
        $value3 = Setting::get('cached_key');
        $this->assertEquals('new_value', $value3);
    }

    public function test_can_handle_different_types(): void
    {
        // Boolean
        Setting::set('bool_setting', true, 'boolean');
        $this->assertTrue(Setting::get('bool_setting'));
        
        Setting::set('bool_setting', false, 'boolean');
        $this->assertFalse(Setting::get('bool_setting'));
        
        // Integer
        Setting::set('int_setting', 42, 'integer');
        $this->assertSame(42, Setting::get('int_setting'));
        
        // Float
        Setting::set('float_setting', 3.14, 'float');
        $this->assertSame(3.14, Setting::get('float_setting'));
        
        // Array/JSON
        $array = ['key' => 'value', 'nested' => ['data' => true]];
        Setting::set('json_setting', $array, 'json');
        $this->assertEquals($array, Setting::get('json_setting'));
    }

    public function test_clear_cache_removes_all_cached_settings(): void
    {
        Setting::set('key1', 'value1');
        Setting::set('key2', 'value2');
        
        // Ensure values are cached
        Setting::get('key1');
        Setting::get('key2');
        
        // Clear all cache
        Setting::clearCache();
        
        // Update database directly
        Setting::where('key', 'key1')->update(['value' => 'new_value1']);
        Setting::where('key', 'key2')->update(['value' => 'new_value2']);
        
        // Should get new values (not cached)
        $this->assertEquals('new_value1', Setting::get('key1'));
        $this->assertEquals('new_value2', Setting::get('key2'));
    }

    public function test_claude_api_settings(): void
    {
        Setting::set('claude_api_key', 'sk-ant-test-key', 'string', 'claude');
        Setting::set('claude_model', 'claude-3-5-sonnet-20241022', 'string', 'claude');
        Setting::set('claude_streaming', true, 'boolean', 'claude');
        Setting::set('claude_max_tokens', 4096, 'integer', 'claude');
        Setting::set('claude_temperature', 0.7, 'float', 'claude');
        
        $this->assertEquals('sk-ant-test-key', Setting::get('claude_api_key'));
        $this->assertEquals('claude-3-5-sonnet-20241022', Setting::get('claude_model'));
        $this->assertTrue(Setting::get('claude_streaming'));
        $this->assertEquals(4096, Setting::get('claude_max_tokens'));
        $this->assertEquals(0.7, Setting::get('claude_temperature'));
    }
}