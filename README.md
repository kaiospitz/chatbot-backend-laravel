## Chatbot

Talk to Yoda.

Chatbot coded in Vue.js, Nuxt and Laravel.

Live Demo: https://kaiospitz.com.br/yodabot/

The frontend can be found here: https://github.com/kaiospitz/chatbot-frontend-vue-nuxt/

## Build Setup

```bash
# install dependencies
composer install

# create .env file
cp .env.example .env

# generate secret key
php artisan key:generate

# create database
php artisan migrate

# run the server at localhost:8000
php artisan serve
```
