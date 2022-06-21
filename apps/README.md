### Application list

1. AB.SKY application
2. .. others comming soon

AB.SKY use 4 layouts: for desktop, mobile, printing and for SVG images.
After installation and using MySQL database try switch to SQLite (edit main/conf.php)

### How to install

Create an empty dir in the Apache root: `mkdir ab`, then `mkdir ab/web`.
Extract files from ab.zip.
Put in the last folder this two files: **ab.sky** and **moon.php**.
Open in browser similar like `http://my-apache.local/ab/web/moon.php`, then follow the instructions.

### To do

Only PHP required.. Install apps from console this way: `php moon.php ab.sky`.
After finish, you will see opened web-browser with working application. Will used internal PHP's web-server and Sqlite database.
