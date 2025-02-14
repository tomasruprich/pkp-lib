<?php

/**
 * @file classes/migration/AnnouncementsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AnnouncementsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as Schema;

class AnnouncementsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Announcement types.
        Schema::create('announcement_types', function (Blueprint $table) {
            $table->bigInteger('type_id')->autoIncrement();
            $table->smallInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->index(['assoc_type', 'assoc_id'], 'announcement_types_assoc');
        });

        // Locale-specific announcement type data
        Schema::create('announcement_type_settings', function (Blueprint $table) {
            $table->bigInteger('type_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6);
            $table->index(['type_id'], 'announcement_type_settings_type_id');
            $table->unique(['type_id', 'locale', 'setting_name'], 'announcement_type_settings_pkey');
        });

        // Announcements.
        Schema::create('announcements', function (Blueprint $table) {
            $table->bigInteger('announcement_id')->autoIncrement();
            //  NOT NULL not included for upgrade purposes
            $table->smallInteger('assoc_type')->nullable();
            $table->bigInteger('assoc_id');
            $table->bigInteger('type_id')->nullable();
            $table->date('date_expire')->nullable();
            $table->datetime('date_posted');
            $table->index(['assoc_type', 'assoc_id'], 'announcements_assoc');
        });

        // Locale-specific announcement data
        Schema::create('announcement_settings', function (Blueprint $table) {
            $table->bigInteger('announcement_id');
            $table->string('locale', 14)->default('');
            $table->string('setting_name', 255);
            $table->text('setting_value')->nullable();
            $table->string('setting_type', 6)->nullable();
            $table->index(['announcement_id'], 'announcement_settings_announcement_id');
            $table->unique(['announcement_id', 'locale', 'setting_name'], 'announcement_settings_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        Schema::drop('announcement_types');
        Schema::drop('announcement_type_settings');
        Schema::drop('announcements');
        Schema::drop('announcement_settings');
    }
}
