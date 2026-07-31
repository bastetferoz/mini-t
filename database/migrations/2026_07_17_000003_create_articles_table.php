<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category');           // infraestructura, redes, servidores, seguridad, procedimientos, finops, otro
            $table->string('subcategory')->nullable(); // vmware, cisco, malware, etc.
            $table->json('tags')->nullable();
            $table->longText('body');             // Markdown
            $table->string('status')->default('published'); // draft, published, obsolete
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('attachments')->nullable(); // paths de archivos adjuntos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
