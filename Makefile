danx-spa:
	cd spa && yarn add quasar-ui-danx

danx-core:
	vendor/bin/sail composer require newms87/danx
	vendor/bin/sail artisan danx:link

danx: danx-spa danx-core

queue-scale:
	docker-compose up -d --scale queue-worker=10

queue-restart:
	vendor/bin/sail artisan queue:restart

queue-scale-restart: queue-restart queue-scale

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
