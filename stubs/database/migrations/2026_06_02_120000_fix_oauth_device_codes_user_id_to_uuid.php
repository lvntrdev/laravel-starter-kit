<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration for installs created before oauth_device_codes.user_id was
 * fixed to match the UUID users.id.
 *
 * The original create migration declared `user_id` via foreignId() (bigint).
 * With UUID users that column can NEVER hold a valid identifier, so any stored
 * value is garbage. This migration converts the column to uuid to match
 * users.id — but ONLY when it detects the actual mismatch, so it is a no-op on:
 *   - fresh installs (the column is already uuid), and
 *   - apps that intentionally run integer-keyed users (the column stays bigint).
 *
 * Forward-only repair (not a destructive drop): the column name is preserved
 * and the only data discarded is values that were already invalid by definition.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('oauth_device_codes') || ! $schema->hasTable('users')) {
            return;
        }

        // Only act on the genuine mismatch: UUID users but an integer user_id FK.
        if (! $this->isUuidColumn('users', 'id') || ! $this->isIntegerColumn('oauth_device_codes', 'user_id')) {
            return;
        }

        // Legacy integer values can never reference a UUID user — null the
        // garbage before the type change so it converts cleanly.
        DB::connection($this->getConnection())
            ->table('oauth_device_codes')
            ->whereNotNull('user_id')
            ->update(['user_id' => null]);

        $schema->table('oauth_device_codes', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('oauth_device_codes')) {
            return;
        }

        // Restore the legacy integer column (rollback to the pre-fix schema).
        if ($this->isUuidColumn('oauth_device_codes', 'user_id')) {
            $schema->table('oauth_device_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    /**
     * Whether the column reports a UUID-compatible (char/string) type.
     */
    private function isUuidColumn(string $table, string $column): bool
    {
        $type = strtolower(Schema::connection($this->getConnection())->getColumnType($table, $column));

        return str_contains($type, 'char') || str_contains($type, 'string')
            || str_contains($type, 'uuid') || str_contains($type, 'guid');
    }

    /**
     * Whether the column reports an integer (bigint/int) type.
     */
    private function isIntegerColumn(string $table, string $column): bool
    {
        $type = strtolower(Schema::connection($this->getConnection())->getColumnType($table, $column));

        return str_contains($type, 'int');
    }

    /**
     * Run on the same connection Passport uses for its OAuth tables.
     */
    public function getConnection(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
};
