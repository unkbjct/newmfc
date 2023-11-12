# Установка

чтобы использовать приложение нужно скачать репозиторий и установить все библиотеки:
`composer install`

После в корне проекта создать файл **.env** и перенести туда все из файла  **.env.example**


###Консоль
---
Сгенерировать ключ приложения: `php artisan key:generate`

Выполнить миграцию таблиц:
`php artisan migrate`

Добавить администратора:
`php artisan make:filament-user`

Запустить работу очередей:
`php artisan queue:work`

### Технологии
---
Приложение написано на **Laravel 10.10**
Используемые библиотеки:

- [filament/filament] - ^3.0-stable
- [maatwebsite/excel] - ^3.1_