#!/bin/bash

firewall-cmd --zone=public --add-port=10024/tcp --permanent
firewall-cmd --zone=public --add-port=10025/tcp --permanent
firewall-cmd --zone=public --add-port=8000/tcp --permanent 
firewall-cmd --zone=public --add-port=8010/tcp --permanent 
firewall-cmd --zone=public --add-port=7000/tcp --permanent 
firewall-cmd --zone=public --add-port=7010/tcp --permanent 
firewall-cmd --zone=public --add-port=8020/tcp --permanent 
firewall-cmd --zone=public --add-port=993/tcp --permanent 
firewall-cmd --zone=public --add-port=995/tcp --permanent 
firewall-cmd --zone=public --add-port=587/tcp --permanent 
firewall-cmd --zone=public --add-port=465/tcp --permanent 
firewall-cmd --zone=public --add-port=143/tcp --permanent 
firewall-cmd --zone=public --add-port=110/tcp --permanent 
firewall-cmd --zone=public --add-port=25/tcp --permanent 
firewall-cmd --zone=public --add-port=22/tcp --permanent
firewall-cmd --zone=public --add-port=80/tcp --permanent 
firewall-cmd --zone=public --add-port=443/tcp --permanent 
firewall-cmd --reload

chown -R vmail:vmail /ewomail/mail
chown -R www:www /ewomail/nginx /ewomail/www

/bin/cp -f /root/EwoMail/ewomail-admin/upload/install.sql /ewomail/www/ewomail-admin/upload/

cd /root/EwoMail/install

sed -i "s/ROOTPASSWORD/"${MYSQL_ROOT_PASSWORD}"/g" ./init.php

sed -i "s/ewomail.cn/"${DOMAIN}"/g" /usr/local/dovecot/share/doc/dovecot/dovecot-openssl.cnf

sed -i "s/mysql:host=127\.0\.0\.1/mysql:host=ewomail_mysql/g" $(find /ewomail -name application.ini)
sed -i 's/pdo_password = "root"/pdo_password = "'${MYSQL_ROOT_PASSWORD}'"/g' $(find /ewomail -name application.ini)
sed -i "s/mysql:host=127\.0\.0\.1/mysql:host=ewomail_mysql/g" $(find /ewomail -name Application.php)
sed -i "s/'root', ''/'root', '"${MYSQL_ROOT_PASSWORD}"'/g" $(find /ewomail -name Application.php)

chmod -R 700 ./init.php
./init.php ${DOMAIN} > init_php.log

echo "Initialization Completed"
