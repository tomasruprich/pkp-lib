<?php

/**
 * @file classes/migration/ScheduledTasksMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScheduledTasksMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ScheduledTasksMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // The last run times of all scheduled tasks.
        Schema::create('scheduled_tasks', function (Blueprint $table) {
            $table->string('class_name', 255);
            $table->datetime('last_run')->nullable();
            $table->unique(['class_name'], 'scheduled_tasks_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        Schema::drop('scheduled_tasks');
    }
}
