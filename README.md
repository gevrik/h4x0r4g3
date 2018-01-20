# h4x0r4g3
HTML5 Websocket Game

Project is at a very early development stage. Not ready for production or even alpha testing.

Copy config/autoload/local.php.dist to config/autoload/local.php and make adjustments according to your environment.

Create a database and import the latest _fresh db file from scripts folder (alternatively, create your database and use Doctrine's schema tool).

Check with Doctrine's schema tool commands to see if any changes to the database are needed and update db accordingly.

Installation commands after using latest _fresh db:

php public/index.php init-server<br/>
php public/index.php create-chatsubo<br/>
php public/index.php create-faction-systems<br/>
php public/index.php create-admin-account<br/>
php public/index.php create-main-campaign-npcs

Now you can start the server:

php public/index.php start-websocket
