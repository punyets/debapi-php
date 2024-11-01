<?
namespace Models\Schema;
namespace Models\Tables;

// So far this only supports SQL. And we should decide what programming 
// language to translate this whole codebase into so that we can know the 
// builtin functions to use for setting up the nasty database server 
// handling stuffs.

include "db.prefabs/db.schema.prefab.php";
include 'db.prefabs/db.table.prefab.php';
include 'db.schema.model.php';

// namespace stuffs, so that I don't have to write "\Prefabs\Schema" everytime I write "Schema";
use \Prefabs\Schema;
use \Prefabs\Table;
use \Field;

// Where going to connect to an SQL database server
$TheSQL = new Schema(
	dbname: "databasename",
	host: "localhost",
	port: "8080",
	username: "userperson"
);

// this initialises everything, 
$TheSQL->init();

class SomeTable { use Table; // Table is the template for a class which will represent a database table, this is special for PHP. I bet we need to find a new way of 
	// declaring a table with a new language if we are going to change into a new one. Choosing JavaScript imples making changes on how this class is declared, because 
	// as far as I know it can only do inheritance of classes.
	static function indict()
	{
		self::Model(
			tablename: "sometable",
			fields: [ // => is not arrow function, just heads up. It's for key-value pairs.
				'row_id' => Field::PrimaryKey(name: 'rowid', null: false, auto_increment: true),
				'name' => Field::Text(name: 'password')
			]
		);
	}
}

// returns all entries within that hypothetical table
$query = SomeTable::entries()->all();

// get a specific entry through the primary key
$query = SomeTable::entries()->filter(row_id: 1);
$query = SomeTable::entries()->get(1);

// ...WHERE `name` LIKE "robert"
$query = SomeTable::entries()->filter(name__like: "robert");

// 'row_id' always becomes 'row_id__eq'.
// look at db.query.arbiter.php file, and find the constant variable FILTER_KWORDS to see more suffixes.
//
// I really tried to make this library be modular, so that it can be scaled easily to add more features with 
// like join and subquery and stuffs.
//
// wrote this within a week. then less than a month of finding new bugs (polishing).
//
// the comments on those files are from 2 years ago.
