# GPT Manager

## Setup

### Laravel API

```
sail up -d
sail artisan migrate:fresh --seed
sail artisan storage:link
```

### SPA

```
cd spa
yarn
yarn dev
```

### IDE

#### Disable automatic formatting on subdirectories

* If you try opening the root directory and the SPA directory at the same time, and you enable automatic reformatting,
  you must disable the reformatting from the root directory on the SPA (or subdirectories of the project that are opened
  in another IDE). These can cause issues with the undo command.

### Scaling Up worker queues

```
docker-compose up -d --scale queue-worker=3
```

