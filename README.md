# MSDev SQL to OData

A PHP library for converting SQL queries to OData (Open Data Protocol) query syntax. Targets **OData 4.01** at the intermediate conformance level, primarily designed for use with the Claris FileMaker OData API (FileMaker Server 2025 / v22+).

We would be very interested in hearing from potential users who have access to alternative OData implementations and may be interested in working to extend this library to ensure that it is broadly useful as possible. 

## Requirements

- PHP ^8.4
- Composer

## Installation

```bash
composer require matatirosoln/sql-to-odata
```

## Usage

Call `parse()` on any supported SQL statement. It returns a typed query object you can inspect to build your OData request.

```php
use Matatirosoln\SqlToOdata\SqlToOdata;

$converter = new SqlToOdata();
$query = $converter->parse($sql);
```

### SELECT

Returns a `SelectQuery` with `entitySet` and `queryString` properties.

```php
use Matatirosoln\SqlToOdata\Query\SelectQuery;

$query = $converter->parse("SELECT Id, Name FROM Users WHERE Status = 'Active' ORDER BY Name ASC LIMIT 10");
// $query->entitySet   => 'Users'
// $query->queryString => '?$select=Id,Name&$filter=Status eq \'Active\'&$orderby=Name asc&$top=10'

assert($query instanceof SelectQuery);
```

### INSERT

Returns an `InsertQuery` with `entitySet` and `body` properties. `body` is an associative array of column-value pairs with PHP-native types, suitable for JSON-encoding into a POST request body.

```php
use Matatirosoln\SqlToOdata\Query\InsertQuery;

$query = $converter->parse("INSERT INTO Users (Name, Age, Active) VALUES ('John', 30, true)");
// $query->entitySet => 'Users'
// $query->body      => ['Name' => 'John', 'Age' => 30, 'Active' => true]

assert($query instanceof InsertQuery);
```

### UPDATE

Returns an `UpdateQuery` with `entitySet`, `body`, and `filter` properties. `filter` is an OData filter expression derived from the WHERE clause. UPDATE without a WHERE clause throws a `ConversionException`.

```php
use Matatirosoln\SqlToOdata\Query\UpdateQuery;

$query = $converter->parse("UPDATE Users SET Name = 'Jane', Age = 31 WHERE Id = 1");
// $query->entitySet => 'Users'
// $query->body      => ['Name' => 'Jane', 'Age' => 31]
// $query->filter    => 'Id eq 1'

assert($query instanceof UpdateQuery);
```

### DELETE

Returns a `DeleteQuery` with `entitySet` and `filter` properties. DELETE without a WHERE clause throws a `ConversionException`.

```php
use Matatirosoln\SqlToOdata\Query\DeleteQuery;

$query = $converter->parse('DELETE FROM Users WHERE Id = 1');
// $query->entitySet => 'Users'
// $query->filter    => 'Id eq 1'

assert($query instanceof DeleteQuery);
```

### Dispatching on query type

Use `match` or `instanceof` checks to handle each statement type:

```php
use Matatirosoln\SqlToOdata\Query\DeleteQuery;
use Matatirosoln\SqlToOdata\Query\InsertQuery;
use Matatirosoln\SqlToOdata\Query\SelectQuery;
use Matatirosoln\SqlToOdata\Query\UpdateQuery;

$query = $converter->parse($sql);

match (true) {
    $query instanceof SelectQuery => handleSelect($query),
    $query instanceof InsertQuery => handleInsert($query),
    $query instanceof UpdateQuery => handleUpdate($query),
    $query instanceof DeleteQuery => handleDelete($query),
};
```

## Supported SQL features

> Targets OData 4.01 intermediate conformance level ([spec](http://docs.oasis-open.org/odata/odata/v4.01/os/part1-protocol/odata-v4.01-os-part1-protocol.html)).

### SELECT clauses

| SQL | OData |
|-----|-------|
| `SELECT col1, col2` | `$select=col1,col2` |
| `SELECT *` | *(omitted — returns all fields)* |
| `WHERE` | `$filter` |
| `ORDER BY` | `$orderby` |
| `LIMIT n` | `$top=n` |
| `LIMIT n OFFSET m` | `$top=n&$skip=m` |

### WHERE operators

| SQL | OData |
|-----|-------|
| `=` | `eq` |
| `!=` / `<>` | `ne` |
| `>` | `gt` |
| `>=` | `ge` |
| `<` | `lt` |
| `<=` | `le` |
| `AND` / `OR` | `and` / `or` |
| `IS NULL` | `eq null` |
| `IS NOT NULL` | `ne null` |
| `LIKE '%val%'` | `contains(col, 'val')` |
| `LIKE 'val%'` | `startswith(col, 'val')` |
| `LIKE '%val'` | `endswith(col, 'val')` |
| `IN (a, b, c)` | `(col eq a or col eq b or col eq c)` |

## Exceptions

All errors throw `Matatirosoln\SqlToOdata\Exception\ConversionException`. Common cases:

- Invalid or unparseable SQL
- UPDATE or DELETE without a WHERE clause
- Subqueries (not supported)
- Unsupported statement types (e.g. `CREATE`, `DROP`)

## Running tests

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).

## Contact

Steve Winter — Matatiro Solutions Ltd — [steve@msdev.nz](mailto:steve@msdev.nz)
