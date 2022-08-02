## Application list

1. [AB.SKY][1] application
2. [MED.CRM.SKY][2] application
3. [HOLE.SKY][3] - empty application

AB.SKY use 4 layouts: for desktop, mobile, printing and for SVG images.
After installation and using MySQL database try switch to SQLite (edit main/conf.php)

### How to install

Create an empty dir in the Apache root: `mkdir ab`, then `mkdir ab/web`.
Extract files from ab.zip.
Put in the last folder this two files: **ab.sky** and **moon.php**.
Open in browser similar like `http://my-apache.local/ab/web/moon.php`, then follow the instructions.

### Just PHP required

Create an empty dir somewhere: `mkdir hole`, +one more `mkdir hole/public`. Extract files from hole.zip to the last.

For HOLE.SKY only: you can just type in console (will work embedded into PHP's SQLite3 database and PHP's web-server):

```
php moon.php hole.sky
```

Or.. install & run HOLE.SKY via **composer**:

```
composer create-project coresky/hole test
cd test/public
php ../vendor/energy/air/sky s
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

[1]: https://coresky.net/api?get=ab.zip
[2]: https://coresky.net/api?get=medcrm.zip
[3]: https://coresky.net/api?get=hole.zip

