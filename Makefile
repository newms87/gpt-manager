
danx:
	vendor/bin/sail composer require flytedan/danx-laravel

refresh-data:
	vendor/bin/sail artisan migrate:refresh --seed
