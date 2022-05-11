## Installation

**docker-compose.yaml [development]**

````
1. cp .env.example .env 

2. cp www/.env.example www/.env

3. docker volume create --name=akpoker-backend-redis_data --driver=local

4. docker volume create --name=akpoker-backend-postgres_data --driver=local

5. docker-compose up -d --build

6. docker-compose run --rm workspace composer install

7. docker-compose run --rm yii migrate

8. docker-compose run --rm yii rbac/init

9. docker-compose run --rm yii data/init

````
