<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

	public function up()
	{
		if (!Schema::hasTable('api_logs')) {
			Schema::create('api_logs', function (Blueprint $table) {
				$table->bigIncrements('id');
				$table->unsignedInteger('audit_request_id')->nullable();
				$table->unsignedInteger('user_id')->nullable()->index();
				$table->string('api_class');
				$table->string('service_name');
				$table->unsignedInteger('status_code');
				$table->string('method');
				$table->string('url', 512)->index();
				$table->text('full_url');
				$table->json('request')->nullable();
				$table->json('response')->nullable();
				$table->json('request_headers')->nullable();
				$table->json('response_headers')->nullable();
				$table->json('stack_trace')->nullable();
				$table->timestamps();

				$table->index(['api_class', 'status_code', 'method']);
				$table->index(['service_name', 'status_code', 'method']);
			});
		}

		try {
			Schema::table('job_dispatch', function (Blueprint $table) {
				$table->foreign(['job_batch_id'])->references(['id'])->on('job_batches')->onUpdate('no action')->onDelete('no action');
				$table->foreign(['user_id'])->references(['id'])->on('users')->onUpdate('no action')->onDelete('no action');
			});
		} catch(Throwable $e) {
			// Ignore
		}

		/**
		 * CREATE TABLE `file`
		 * (
		 * `id`                      char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci     NOT NULL,
		 * `disk`                    varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci  NOT NULL,
		 * `filepath`                varchar(768) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		 * `filename`                varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		 * `url`                     varchar(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci          DEFAULT NULL,
		 * `mime`                    varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
		 * `transcodes`              json                                                                   DEFAULT NULL,
		 * `requires_transcode`      tinyint(1)                                                    NOT NULL DEFAULT '0',
		 * `is_transcode_complete`   tinyint(1)                                                    NOT NULL DEFAULT '0',
		 * `transcoding_start_at`    datetime                                                               DEFAULT NULL,
		 * `transcode_failed_at`     datetime                                                               DEFAULT NULL,
		 * `transcode_failed_count`  int unsigned                                                  NOT NULL DEFAULT '0',
		 * `transcode_failed_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
		 * `transcode_paused`        tinyint(1)                                                    NOT NULL DEFAULT '0',
		 * `size`                    int unsigned                                                  NOT NULL,
		 * `exif`                    json                                                                   DEFAULT NULL,
		 * `meta`                    json                                                                   DEFAULT NULL,
		 * `location`                json                                                                   DEFAULT NULL,
		 * `storable_id`             char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci              DEFAULT NULL,
		 * `storable_type`           varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci          DEFAULT NULL,
		 * `storable_subtype`        varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci          DEFAULT NULL,
		 * `created_at`              timestamp                                                     NULL     DEFAULT NULL,
		 * `updated_at`              timestamp                                                     NULL     DEFAULT NULL,
		 * `deleted_at`              timestamp                                                     NULL     DEFAULT NULL,
		 * PRIMARY KEY (`id`),
		 * KEY `file_filepath_index` (`filepath`),
		 * KEY `file_storable_index` (`storable_type`, `storable_id`),
		 * KEY `file_storable_subtype_index` (`storable_subtype`),
		 * KEY `file_requires_transcode_is_transcode_complete_index` (`requires_transcode`, `is_transcode_complete`)
		 * ) ENGINE = InnoDB
		 * DEFAULT CHARSET = utf8mb4
		 * COLLATE = utf8mb4_unicode_ci;
		 */
		if (!Schema::hasTable('stored_files')) {
			Schema::create('stored_files', function (Blueprint $table) {
				$table->uuid()->primary();
				$table->string('disk', 36);
				$table->string('filepath', 768)->index('file_filepath_index');
				$table->string('filename', 255);
				$table->string('url', 512)->nullable();
				$table->string('mime', 255);
				$table->unsignedInteger('size')->default(0);
				$table->json('exif')->nullable();
				$table->json('meta')->nullable();
				$table->json('location')->nullable();
				$table->uuid('storable_id')->nullable();
				$table->string('storable_type', 255)->nullable();
				$table->string('category', 255)->nullable();
				$table->timestamps();
				$table->timestamp('deleted_at')->nullable();

				$table->index(['storable_id', 'storable_type'], 'file_storable_index');
			});
		}
	}

	public function down()
	{
		Schema::dropIfExists('stored_files');
	}
};
