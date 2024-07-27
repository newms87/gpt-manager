danx:
	vendor/bin/sail composer require newms87/danx
	vendor/bin/sail artisan fix
	cd spa && yarn add quasar-ui-danx

refresh-data:
	vendor/bin/sail artisan migrate:refresh --seed

deploy-vapor:
	sh ./deploy.sh

deploy-spa:
	cd spa && sh ./deploy.sh

deploy: deploy-vapor deploy-spa

dump:
	vendor/bin/sail artisan dump

work:
	vendor/bin/sail artisan queue:work
