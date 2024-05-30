
danx:
	vendor/bin/sail composer require flytedan/danx-laravel

refresh-data:
	vendor/bin/sail artisan migrate:refresh --seed

deploy-vapor:
	sh ./deploy.sh

deploy-spa:
	cd spa && sh ./deploy.sh

deploy: deploy-vapor deploy-spa
