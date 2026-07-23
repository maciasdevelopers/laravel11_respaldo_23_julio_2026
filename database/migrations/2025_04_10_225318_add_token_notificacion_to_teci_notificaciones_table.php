<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('teci_notificaciones', function (Blueprint $table) {
            $table->string('token_notificacion')->nullable(); // Puede ser nullable o requerido según tus necesidades
        });
    }
    
    public function down()
    {
        Schema::table('teci_notificaciones', function (Blueprint $table) {
            $table->dropColumn('token_notificacion');
        });
    }
};
