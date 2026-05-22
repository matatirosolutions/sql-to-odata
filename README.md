# MSDev SQL to OData

> **Work in progress.** Only `SELECT` queries are currently supported. Additional statement types are planned.

A PHP library for converting SQL queries to OData (Open Data Protocol) query syntax, designed for use in Microsoft Dynamics and related Microsoft development environments.

## Requirements

- PHP ^8.4
- Composer

## Installation

```bash
composer require matatirosoln/sql-to-odata
```

## Usage

```php
use Matatirosoln\SqlToOdata\SqlToOdata;

$converter = new SqlToOdata();

$odata = $converter->convert("SELECT Id, Name FROM Users WHERE Status = 'Active' ORDER BY Name ASC LIMIT 10");
// ?$select=Id,Name&$filter=Status eq 'Active'&$orderby=Name asc&$top=10
```

## Supported SQL clauses

| SQL | OData |
|-----|-------|
| `SELECT col1, col2` | `$select=col1,col2` |
| `WHERE` | `$filter` |
| `ORDER BY` | `$orderby` |
| `LIMIT n` | `$top=n` |
| `LIMIT n OFFSET m` | `$top=n&$skip=m` |

### Supported `WHERE` operators

| SQL | OData |
|-----|-------|
| `=` | `eq` |
| `!=` / `<>` | `ne` |
| `>` | `gt` |
| `>=` | `ge` |
| `<` | `lt` |
| `<=` | `le` |
| `AND` / `OR` | `and` / `or` |

## Running tests

```bash
composer install
./vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).

## Contact

Steve Winter — Matatiro Solutions Ltd — [steve@msdev.nz](mailto:steve@msdev.nz)
