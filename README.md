#BananaDB

*A class for easy and secure access to MySQL from PHP.*

Copyright (C) 2015 LaNsHoR lanshor@gmail.com

This program is free software: you can redistribute it and/or modify it under the terms of the MIT License (see the code for more info).

##Goals

1. **SQL injection protection**. BananaDB protects you and makes SQL injection attacks 100% impossible.
2. **Easy prepared statements**. With BananaDB, the use of prepared statements is automatic and trivial.
3. **Ultra simple set up**. One file, one include, lot of fun.
4. **SQL syntax**. Write your PHP code like SQL. Do more with less lines. Gains readability.
5. **Exceptions ready**. If something goes wrong, BananaDB throws a exception with a descriptive message.

## Set Up

```php
require_once("banana_db.php");
$bdb = new BananaDB("localhost", "my_user", "my_password", "my_database");
```

That's all folks!

If you don't like the bananas, you can also use the class alias BDB.

```php
$bdb = new BDB("localhost", "my_user", "my_password", "my_database");
```

More pro. Less funny.

## Select

### Basic select

```php
$all_my_fruits = $bdb->select("*")->from("fruits")->exec();
```

or

```php
$bdb->select("*")->from("fruits");
$all_my_fruits = $bdb->exec();
```

Both are the same. Easy, right?

### Reading a row

```php
$first_fruit_row = $all_my_fruits[0]; //The first line of the table
$second_fruit_row = $all_my_fruits[1]; //The second line of the table
//... etc
```

As you can see, it's so easy. Surely you can imagine how to read a field. You probably don't need to read the next section :)

### Reading a field from a row

```php
$id_of_first_fruit = $first_fruit_row["id_fruit"];
```
or
```php
$id_of_frist_fruit = $first_fruit_row[0]; //id_fruit is the first column of the table, them index = 0
```

You can read a field through the name or index. Both are the same.

### Take what you want

You can execute your query with:

- **exec**: returns a two dimensional array with the result table.
- **exec_one_row**: returns an associative array with the first row of the result.
- **exec_one_field**: returns the first value, of the first row, of the result.

```php
$fruit_name = $bdb->select("name")->from("fruits")->where("id_fruit = 5")->and("active = 1")->exec_one_field();
```

Get values in only one line. As it always should be.

### Using variables

In order to use variables in your query, you need to extract them from the strings.

```php
$id_fruit = 5;
$color = "red";
$fruit_name = $bdb->select("name")->from("fruits")->where("id_fruit =", $id_fruit)->and("active = 1")->or("color =", $color)->exec_one_field();
```

This protects you from SQL injection attacks and enable the possibility of use prepared statements (see below).

**WARNING 1**: Variables inside strings are not checked and makes your code vulnerable. Extract them. **ALWAYS**.

Repeat with me: **A-L-W-A-Y-S**.

```php
//Right, perfect, wonderful :)
$fruit_name = $bdb->select("name")->from("fruits")->where("id_fruit =", $id_fruit)->exec_one_field();

//WRONG!! NEVER!! SIN!! APOCALYPSE!! THE END OF THE WORLD!!
$fruit_name = $bdb->select("name")->from("fruits")->where("id_fruit = $id_fruit")->exec_one_field();
```

### Learn more about select with one example

```php
$bdb->select("color", "sum(price)/count(*) as average_price")->from("fruits f", "prices p")->where("f.id_fruit = p.id_fruit")->group_by("color")->having("average_price < 1.5")->exec();
```

also with only one string parameter for select, for from... etc

```php
$bdb->select("color, sum(price)/count(*) as average_price")->from("fruits f, prices p")->where("f.id_fruit = p.id_fruit")->group_by("color")->having("average_price < 1.5")->exec();
```

Both are the same. I love it.

Remember in PHP methods names are case-insensitive, like column names in MySQL:

```php
$bdb->SELECT("COLOR, SUM(PRICE)/count(*)")->FRom("FRUITS F, prices p")->wHeRe("F.id_fruit = p.ID_FRUIT")->group_BY("color")->exec();
```

Write as you want.

## Update

Example without external data:

```php
$bdb->update("fruits")->set("active = 0")->where("id_fruit = 5")->exec();
```
As always, don't forget to add quotes when you are setting a string literally. Just like MySQL.

```php
$bdb->update("fruits")->set(" name = 'coconut', active = 0 ")->where("id_fruit = 5")->exec();
```

or you can use more than one parameter (on this way you don't need to add quotes)

```php
$bdb->update("fruits")->set("name =", "coconut")->where("id_fruit = 5")->exec();
```

Example with external data (with variables):

```php
//Get data
$name=$_GET["name"];
$id_fruit=$_GET["id_fruit"];
//Execute update query
$bdb->update("fruits")->set("name = ", $name)->where("id_fruit = ", $id_fruit)->exec();
```

And don't cry anymore. Don't worry about SQL injections :)

**WARNING 2**: Don't forget to add the equal character after the field name when you are updating. **Just like MySQL** is our motto.

**WARNING 1 (AGAIN)**: Remember that variables inside strings are not checked. Use a single parameter for each variable, like in examples.

##### Updating more than one field with variables (PHP 5.4+ Version) [RECOMMENDED]

```php
$bdb->update("fruits")->set( ["active =", $active], ["name =", $name] )->where("id_fruit = 5")->exec();
```

##### Updating more than one field with variables (All PHP Versions)

```php
$bdb->update("fruits")->set( array("active =", $active), array("name =", $name) )->where("id_fruit = 5")->exec();
```

Yes, PHP < 5.4 syntax is so ugly. Please, update your PHP version ASAP.

## Insert

As you can imagine...

```php
$bdb->insert_into("fruits")->values(null, "tomato", 4.2, $my_variable)->exec();
```

or with custom fields...

```php
//PHP 5.4+ Version
$bdb->insert_into("fruits")->values( ["name", $name], ["price", 5.3] )->exec();
//PHP 5.4- Version
$bdb->insert_into("fruits")->values( array("name", $name), array("price", 5.3) )->exec();
```

Don't use the equal character in your insertions. Again, just like MySQL.

## Insert OR Update

```php
$bdb->insert_into("fruits")->values(null, "tomato", 4.2, $my_variable)->on_duplicate_key_update(['price', 4.2])->exec();
```
This requires a unique index.
Read more about [On Duplicate Key Update](http://dev.mysql.com/doc/refman/5.7/en/insert-on-duplicate.html)

## Escape from the cage

All values are passed to MySQL as strings, if you need to execute some MySQL functions, please, escape them with ! character and BananaDB will send the text to MySQL in raw mode, without quotes, out of a string.

```php
$bdb->update("fruits")->set("updated = ", "!NOW()")->exec();
```
And you can add some literals in your queries, like distinct:

```php
$bdb->select("distinct color")->from("fruits");
```

If you need more control, you can access directly to the driver...

```php
$mysqli_driver = $bdb->mysqli;
```

## Prepared statements

As longer PHP doesn't support references from the method calls, we need to use some hack. The hack is our REF function.

You can use the REF function to send the reference from a variable to BananaDB and use prepared statements as follow:

```php
//data
$fruits=["tomato", "banana", "pear", "cherry", "watermelon", "orange"];
$fruit_name=0;
//make the query with a reference to $fruit_name using the provided REF function
$bdb->insert_into("fruits")->values(REF($fruit_name));

//insert all fruits without remake the query (less code, more performance)
foreach($fruits as $fruit_name)
   $bdb->exec();
```

You can use the REF function for any field, for any query (insert, update...). Simply change the value and call to the exec method again.

BananaDB does **not send the full query to MySQL**. BananaDB creates a **prepared statement**, making the repetitive execution very fast. The param binding and all tricky things are automatically managed for you.

For more information about prepared statements read: http://php.net/manual/en/pdo.prepared-statements.php

I know what you're thinking. How REF function works if PHP doesn't support references in method calls? Well, call it magic or read the code ;)

## Some more... After the execution

```php
$bdb->getLastId();       //returns the last id inserted in auto_increment field
$bdb->getAffectedRows(); //returns the number of affected rows in the last query
```

## Order By & Limit & Offset

All examples are the same thing:

```php
$bdb->select("*")->from("fruits")->order_by("color desc", "name asc")->limit(2)->offset(5);
```
```php
$bdb->select("*")->from("fruits")->order_by("color desc", "name asc")->limit("2")->offset("5");
```
```php
$bdb->select("*")->from("fruits")->order_by("color desc, name asc")->limit(2,5);
```
```php
$bdb->select("*")->from("fruits")->order_by("color desc", "name asc")->limit("2,5");
```

I hope you get the idea.

## The Static Way

You can use the library invoking the last created object directly:

```php
BananaDB::init($host, $user, $password, $database); //Init a new Static Database
$db=BananaDB::getInstance(); //returns the static instance (the last created)
$another_database=new BananaDB($host, $user, $password, $database);
$db=BananaDB::getInstance(); //returns the non-static instance (the last created)
```

Useful for instant access to the database on any place:

```php
BananaDB::getInstance()->update("fruits")->set("quantity = 50")->where("id_fruit =", $id_tomato)->exec();
```

##For the future

TODO:

1. **Joins** support
2. **Not In** support
3. **Sub-queries** support
4. **Transactions** support

## Bugs, comments, and more...

Write to lanshor@gmail.com