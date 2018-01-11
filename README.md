# Cat Farm API
This is a sample REST API I built on the Slim Framework to manage a cat farm.
Cats can be created, updated, deleted, searched, and fed.

### To Run Locally
- Requirements
    - PHP
    - MySQL
    - PDO
    - Apache
    - Composer
- Update settings
    - Create a file `src/public/settings.php` based on sampleSettings.php and set database host, user, pass and dbname as you have configured them
- Build Project
    - This project needs to be built by navigating to the root of the directory and running `composer install`
    - MySQL database and table needs to be created locally
        - ```CREATE TABLE IF NOT EXISTS `cat` ( `id` int(32) unsigned NOT NULL AUTO_INCREMENT, `name` varchar(30) NOT NULL, `age` int(3), `status` varchar(10) NOT NULL DEFAULT 'content', `temperment` varchar(20), `photoUrls` JSON, PRIMARY KEY (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;```
- Run Service
    - Navigate to `src/public/` and run `php -S localhost:8080` from the command line
- Use postman or a similar service to interact with the API
