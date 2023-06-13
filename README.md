# php-pdo-wrapper
Very simple PHP PDO Wrapper with only depedency of PDO

# How to use
Just include the PHP script, connect and query

```php
<?php

// include the script
require ('DB.php');


// connect as DB static instance : connect($host, $user, $pass, $name, $prefix = '', $charset = 'utf8')
DB::connect('db_host', 'db_user', 'db_password', 'db_name');

// or create DB connection as instance : new DB($host, $user, $pass, $name, $prefix = '', $charset = 'utf8')
$db = new DB('db_host', 'db_user', 'db_password', 'db_name');


// select query with static DB instance
DB::table('table_name')->select()->run();
DB::table('table_name')->select()->where('id', 5)->run();
DB::table('table_name')->select()->where('id', '>=', 2)->run();
DB::table('table_name')->select()->where('id', 5)->where('name', 'LIKE', 'Test%')->run();
DB::table('table_name')->select()->where('id', '>=', 1)->orderBy('id')->run();
DB::table('table_name')->select()->where('id', '>=', 1)->orderBy('id', 'DESC')->run();
DB::table('table_name')->select()->run('class_name');

// insert query with static DB instance
DB::table('table_name')->insert(['id' => 1, 'name' => 'Test'])->run();


// update and delete query with static DB instance
DB::table('table_name')->where('id', 1)->update(['name' => 'Test New'])->run();
DB::table('table_name')->where('id', 1)->delete()->run();

// or as non static
$db->table('table_name')->where('id', 1)->update(['name' => 'Test New'])->run();
$db->table('table_name')->where('id', 1)->delete()->run();


// get list of tables in database
DB::tables();

// or as non static
$db->tables();


// get list of column in table
DB::columns('table_name');

// or as non static
$db->columns('table_name');
