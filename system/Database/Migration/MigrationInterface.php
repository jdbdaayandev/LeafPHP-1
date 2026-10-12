<?php
declare(strict_types=1);
namespace LeafPHP\Database\Migration;
interface MigrationInterface { public function up(): void; public function down(): void; }
