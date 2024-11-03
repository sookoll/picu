#!/bin/bash
# Deploy Picu.
# Put this outside htdocs
wget https://github.com/sookoll/picu/archive/refs/heads/master.zip
unzip master.zip
cp htdocs/picu/.env picu-master/src/
cd picu-master/src/
composer install
php vendor/bin/phoenix migrate --config=conf/phoenix.php
cd ../../
rm -rf picu-bak
mv htdocs/picu ./picu-bak
mv picu-master/src htdocs/picu
mv ./picu-bak/media htdocs/picu/
rm -rf master.zip picu-master
