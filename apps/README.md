### Application list

1. AB.SKY application
2. MED.CRM.SKY application
3. HOLE.SKY - empty application

AB.SKY use 4 layouts: for desktop, mobile, printing and for SVG images.
After installation and using MySQL database try switch to SQLite (edit main/conf.php)

### How to install

Create an empty dir in the Apache root: `mkdir ab`, then `mkdir ab/web`.
Extract files from ab.zip.
Put in the last folder this two files: **ab.sky** and **moon.php**.
Open in browser similar like `http://my-apache.local/ab/web/moon.php`, then follow the instructions.

### Just PHP >7 required

Create an empty dir somewhere: `mkdir hole`, +one more `mkdir hole/public`. Extract files from hole.zip to the last.

For HOLE.SKY only: you can just type in console (will work embedded into PHP's SQLite3 database and PHP's web-server):

```
php moon.php hole.sky
```
