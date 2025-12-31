Picu
====

Your personal photo gallery. Can serve photos from Flickr and/or from local disk.

## Setup

1. Copy .env.example to .env
2. Edit .env, use /admin/hash/<pwd> to generate one, if needed
3. Edit .htaccess - RewriteBase if not in root
4. `composer install`
5. `composer migrate`

To create new migration:
```
composer create -- "Migrations\NewMigrationName"
```
To rollback last migration:
```
composer rollback
```

## Deploy

```bash
wget https://github.com/sookoll/picu/archive/refs/heads/master.zip
unzip master.zip
cp htdocs/picu/.env picu-master/src/
cp htdocs/picu/media picu-master/src/media
nano picu-master/src/.env
cd picu-master/src/
composer install
php vendor/bin/phoenix migrate --config=conf/phoenix.php
cd ../../
mv htdocs/picu ./picu-bak
mv picu-master/src htdocs/picu
rm -rf master.zip picu-master
```
