fix-danx-ui:
	cd ../quasar-ui-danx/ui && yarn

danx-spa:
	cd spa && yarn add quasar-ui-danx

danx-core:
	vendor/bin/sail composer require newms87/danx
	vendor/bin/sail artisan danx:link

danx: fix-danx-ui danx-spa danx-core

queue-scale:
	docker-compose stop queue-worker
	docker-compose up -d --scale queue-worker=10

queue-restart:
	vendor/bin/sail artisan queue:restart

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
