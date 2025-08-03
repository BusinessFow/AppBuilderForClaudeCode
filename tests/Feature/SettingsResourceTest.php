<?php

namespace Tests\Feature;

use App\Filament\Resources\Settings\SettingResource;
use App\Filament\Resources\Settings\Pages\CreateSetting;
use App\Filament\Resources\Settings\Pages\EditSetting;
use App\Filament\Resources\Settings\Pages\ListSettings;
use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_list_settings(): void
    {
        Setting::create([
            'key' => 'test_setting',
            'value' => 'test_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $this->get(SettingResource::getUrl('index'))
            ->assertSuccessful()
            ->assertSee('test_setting');
    }

    public function test_can_create_setting(): void
    {
        $this->get(SettingResource::getUrl('create'))
            ->assertSuccessful();

        Livewire::test(CreateSetting::class)
            ->fillForms([
                'key' => 'new_setting',
                'value' => 'new_value',
                'type' => 'string',
                'group' => 'general',
                'description' => 'Test description',
                'is_public' => false,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'new_setting',
            'value' => 'new_value',
        ]);
    }

    public function test_can_edit_setting(): void
    {
        $setting = Setting::create([
            'key' => 'edit_test',
            'value' => 'original_value',
            'type' => 'string',
            'group' => 'general',
        ]);

        $this->get(SettingResource::getUrl('edit', ['record' => $setting]))
            ->assertSuccessful();

        Livewire::test(EditSetting::class, ['record' => $setting->id])
            ->fillForms([
                'value' => 'updated_value',
            ])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('updated_value', $setting->fresh()->value);
    }

    public function test_can_delete_setting(): void
    {
        $setting = Setting::create([
            'key' => 'delete_test',
            'value' => 'to_be_deleted',
            'type' => 'string',
            'group' => 'general',
        ]);

        Livewire::test(EditSetting::class, ['record' => $setting->id])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('settings', [
            'key' => 'delete_test',
        ]);
    }

    public function test_manage_settings_page(): void
    {
        $this->get(SettingResource::getUrl('manage'))
            ->assertSuccessful()
            ->assertSee('Claude API Configuration')
            ->assertSee('General Settings')
            ->assertSee('Features');
    }

    public function test_can_save_settings_from_manage_page(): void
    {
        Livewire::test(ManageSettings::class)
            ->set('data.claude_api_key', 'sk-ant-test-key-123')
            ->set('data.claude_model', 'claude-3-5-sonnet')
            ->set('data.app_name', 'My App')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('sk-ant-test-key-123', Setting::get('claude_api_key'));
        $this->assertEquals('claude-3-5-sonnet', Setting::get('claude_model'));
        $this->assertEquals('My App', Setting::get('app_name'));
    }
}