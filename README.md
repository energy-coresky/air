
# The Coresky PHP Framework

Using the framework, you can create web applications of any type. It contains a lot of new original ideas:
"sky gate", "ghost query", "wares system" and more. By the way: the [Jet view parser][1]
has a release-candidate status and is architecturally more powered than Blade or Twig.

Wellcome for contributors!
This project is my favorite "oil painting"! Not finished yet, but you can use it.

Regards,
Energy

## Documentation

See the [wiki section](https://github.com/energy-coresky/air/wiki) (russian only, open not in frame).

## Application examples

1. [AB.SKY][2] application
2. [MED.CRM.SKY][3] application
3. [HOLE.SKY][4] - empty application

AB (absolute busy) application is product of InfoParc http://absolutebusy.com/ moved into SKY.
AB.SKY use 4 layouts: for desktop, mobile, printing and for SVG images.
After installation and using MySQL database try switch to SQLite (edit main/conf.php)

### How to install

Create an empty dir in the Apache root: `mkdir ab`, then `mkdir ab/web`.
Extract files from ab.zip.
Put to the last folder this two files: **ab.sky** and **moon.php**.
Open in browser similar like `http://my-apache.local/ab/web/moon.php`, then follow the instructions.

### Just PHP & console required

For HOLE.SKY only: will work embedded into PHP's SQLite3 database and PHP's web-server:

With composer:

```bash
composer create-project coresky/hole
# or try latest dev: composer create-project coresky/hole hole "dev-master"
cd hole
# then run PHP's embedded web-server:
php vendor/energy/air/sky s
```
Or with moon:

```bash
curl https://coresky.net/api?get=hole.zip > hole.zip
mkdir -p hole/public
unzip hole.zip -d hole/public
cd hole/public
php moon.php hole.sky
```
Or download all with git:

```bash
# the app
git clone https://github.com/energy-coresky/empty-app.git
# the framework
git clone https://github.com/energy-coresky/air.git
# the wares
mkdir empty-app/wares
cd empty-app/wares
git clone https://github.com/energy-coresky/parsedown.git
git clone https://github.com/energy-coresky/earth.git
git clone https://github.com/energy-coresky/mercury.git
# then run PHP's embedded web-server:
php ../../air/sky s
```

<hr>

Installer **moon.php** uses files with the **.sky** extension. These files contain all the application files and
database contents. It also contains information about the required modules and versions of PHP, MySQL.
You can prepare such files in the developer tools. This operation is called application compilation.

First of all **moon.php**, it may be a convenient way to install SKY applications for non-professionals.
Secondly, if you use a hosting in which there is no SSH access and there are other restrictions,
then **moon.php** - a convenient way to update the site on production for professionals also. In **moon.php** there are several
types of installation. Among them: pre-installation of the application in the **anew** folder, followed by moving
to production. In this case, the code of the old version is moved to the **aold** folder and it is possible to make a rollback.

<hr>

Инсталлятор **moon.php** использует файлы с расширением **.sky**. Эти файлы, содержат все файлы приложения и
содержимое баз данных. Также в нем содержится информация о требуемых модулях и версиях PHP, MySQL.
Подготовить такие файлы можно в инструментах разработчика. Такая операция называется компиляция приложения.

Во-первых **moon.php**, может оказаться удобным способом установки SKY-приложений для непрофессионалов.
Во-вторых, если вы используете хостинг, в котором отсутствует SSH доступ и имеются прочие ограничения,
то **moon.php** - удобный способ обновить сайт на production и для профессионалов. В **moon.php** имеется несколько
типов установки. Среди них: предварительная установка приложения в папку **anew** с последующим перемещением
на production. При этом код старой версии перемещается в папку **aold** и имеется возможность сделать rollback.

[1]: https://github.com/energy-coresky/air/wiki/%D0%A8%D0%B0%D0%B1%D0%BB%D0%BE%D0%BD%D0%B8%D0%B7%D0%B0%D1%82%D0%BE%D1%80-%D0%BF%D1%80%D0%B5%D0%B4%D1%81%D1%82%D0%B0%D0%B2%D0%BB%D0%B5%D0%BD%D0%B8%D0%B9-Jet
[2]: https://coresky.net/api?get=ab.zip
[3]: https://coresky.net/api?get=medcrm.zip
[4]: https://coresky.net/api?get=hole.zip
