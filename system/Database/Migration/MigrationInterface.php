<?php
declare(strict_types=1);

namespace LeafPHP\Database\Migration;

/** Contract every migration file must implement. */
interface MigrationInterface
{
    /** Apply the schema change. */
    public function up(): void;

    /** Reverse the schema change made by up(). */
    public function down(): void;
}
