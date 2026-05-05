<?php

namespace Lvntr\StarterKit\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lvntr\StarterKit\StarterKitServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

/**
 * DB-aware TestCase: SQLite in-memory veritabanı ile çalışır.
 *
 * Hangi tabloları oluşturduğu:
 *   - settings          (stub migration'dan alınan şema)
 *   - media             (Spatie stub + deleted_at soft-delete kolonu)
 *   - file_folders      (vendor migration)
 *   - file_favorites    (vendor migration)
 *   - global_file_buckets (vendor migration)
 *
 * media-library config'inde media_model olarak
 * Lvntr\StarterKit\Tests\Stubs\TestMedia bind edilir; bu model
 * Spatie base Media'ya SoftDeletes ekler — consumer'ın stub Media
 * modelini taklit eder, App namespace gerektirmez.
 */
abstract class DatabaseTestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            StarterKitServiceProvider::class,
            MediaLibraryServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // SQLite in-memory
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Test Media modeli — App namespace gerektirmez
        $app['config']->set('media-library.media_model', \Lvntr\StarterKit\Tests\Stubs\TestMedia::class);

        // Test stub model binding'leri — App\Models\* namespace gerektirmez
        $app['config']->set('file-manager.models.folder', \Lvntr\StarterKit\Tests\Stubs\TestFileFolder::class);
        $app['config']->set('file-manager.models.favorite', \Lvntr\StarterKit\Tests\Stubs\TestFileFavorite::class);

        // file-manager config defaults
        $app['config']->set('file-manager.settings.storage_quota_mb', 10240);
        $app['config']->set('file-manager.settings.max_size_mb', 10);

        // MediaLibrary disk
        $app['config']->set('media-library.disk_name', 'public');
        $app['config']->set('filesystems.disks.public', [
            'driver' => 'local',
            'root'   => storage_path('app/public'),
            'url'    => '/storage',
        ]);

        // Encryption key (Cache/session ihtiyacı için)
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineDatabaseMigrations(): void
    {
        // 1. settings tablosu
        Schema::create('settings', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('group')->index();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->timestamps();
            $table->unique(['group', 'key']);
        });

        // 2. media tablosu (Spatie stub şeması + deleted_at)
        Schema::create('media', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();
            $table->string('folder_id')->nullable()->index(); // FileManager extension
            $table->nullableTimestamps();
            $table->softDeletes(); // consumer Media modeli SoftDeletes kullanıyor
        });

        // 3. Vendor package migration'ları
        $this->loadMigrationsFrom(
            dirname(__DIR__).'/database/migrations'
        );
    }
}
