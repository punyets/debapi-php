<?
namespace Prefabs;

require "core.arbiters/db.adit.arbiter.php";

trait Table 
{ // When defining fields, write values rather than null;
	static TableAdit $entries;

	static private string $Name;
	static private array $Fields;

	private $entry = [];

	final static function create(...$content) : \Query\Sense\QueryType
	{
		self::entries();

		if (count($content) == 0) return NULL;

		return new \Query\Sense\QueryType(
			$content, self::$entries, \Query\Sense\QueryType::EXISTENCE_NEW
		);
	}

	final static function edit(int $pk, ...$content)
	{
		$query_type = self::$entries->get($pk);

		foreach ($content as $key => $val)
		{
			$query_type->{$key} = $val;
		}

		$query_type->save();
	}

	final static function omit(\Query\Sense\QuerySet &$querySet)
	{
		$querySet->omit();
		unset($querySet);
	}

	protected abstract static function indict();

	static private function Model(string $tablename, TableField\BaseField | array ...$fields)
	{
		self::$Name = $tablename;
		self::$Fields = count($fields) > 1 ? $fields : array_values($fields)[0];

		if (is_int(array_key_last(self::$Fields)))
		{
			$field_names = [];
			foreach (self::$Fields as $field)
				array_push($field_names, $field->get_field_name());
			foreach ($field_names as $index => $val)
			{
				self::$Fields[$val] = self::$Fields[$index];
				unset(self::$Fields[$index]);
			}
			unset($field_names);
		}

		unset($index);
		return __CLASS__;
	}


	static function entries() : TableAdit
	{
		self::init();
		return self::$entries;
	}

	static function init()
	{
		if (isset(self::$entries)) return;
		self::indict();
		self::$entries = new TableAdit(Tablename: self::$Name, Fields: self::$Fields);
		\Query\QueryFactory::init();
	}
}
