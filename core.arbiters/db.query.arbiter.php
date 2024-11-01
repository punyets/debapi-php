<?

namespace Query
{ // Query Builder Library. The query statement API?
	require "core.arbiters/db.datatypes.arbiter.php";
	require 'core.arbiters/db.model.arbiter.php';

	require "db.sense-types/QuerySet.type.php";
	require "db.sense-types/QueryType.type.php";

	use Exception;

	use const Query\Statement\ROUTINES;

	class QueryFactory extends TableModel
	{ // Statement Builder and Prepper

		protected string | \PDOStatement $stmnt;

		protected array $stmnt_params = [
			'args-fields' => [],
			'args-types' => [],
			'args-values' => [],
		];
		protected array $cache_stmnt;

		protected string $phrase_filter_struct;

		private static \PDO $CONN;

		public function write_select(?string ...$columns_fields): QueryFactory
		{
			// Check if the arguments are legit fields;
			$columns_real = $this->table_columns_real();
			foreach ($columns_fields as $column)
				if (!in_array($column, $columns_real))
					throw new Exception("Invalid field supplied for QueryFactory::write_select()(). {{$column}} does not exist!");
			unset($columns_real);
			$this->stmnt = Statement\ROUTINES[Routines\SELECT];
			$this->stmnt = sprintf(
				$this->stmnt,
				implode(", ", array_map(fn ($val) => "`$val`", $columns_fields)),
				"`" . $this->table_name() . "`",
			);
			return $this;
		}

		public function write_insert(string ...$columns_fields): QueryFactory
		{
			// Check if the arguments are legit fields;
			$columns_real = $this->table_columns_real();
			foreach ($columns_fields as $column)
				if (!in_array($column, $columns_real))
					throw new Exception("Invalid field supplied for QueryFactory::write_insert(). {{$column}} does not exist!");
			$this->stmnt = Statement\ROUTINES[Routines\INSERT];
			$this->stmnt = sprintf(
				$this->stmnt,
				"`" . $this->Model['tablename'] . "`",
				implode(", ", array_map(fn ($val) => "`$val`", $columns_fields)),
			);
			return $this;
		}

		public function write_update(string | int | float | bool ...$columns_entries): QueryFactory
		{
			$fields_placeholders = $this->placeholder_makr($columns_entries, "edit");
			$this->stmnt = Statement\ROUTINES[Routines\UPDATE];
			$this->stmnt = sprintf(
				$this->stmnt,
				"`" . $this->table_name() . "`",
				implode(", ", array_map(
					fn ($field, $placeholder) => "`$field` = $placeholder",
					array_keys($fields_placeholders),
					array_values($fields_placeholders)
				))
			);
			$this->set_params("edit", ...$columns_entries);
			return $this;
		}

		public function write_delete(): QueryFactory
		{
			$this->stmnt = Statement\ROUTINES[Routines\DELETE];
			$this->stmnt = sprintf(
				$this->stmnt,
				"`" . $this->table_name() . "`"
			);
			return $this;
		}

		public function write_where(string | int | float | bool | null ...$fields_filters): QueryFactory
		{
			if (empty($fields_filters)) return $this;
			$real_fields = [];
			$filter_operators = [];
			foreach ($fields_filters as $field => $val)
			{
				$field = explode("__", $field);
				if (empty((int)end($field)))
					array_push($filter_operators, array_pop($field));
				$real_fields[implode("__", $field)] = $val;
			}
			$fields_placeholders = $this->placeholder_makr($fields_filters, "pos", true);

			// Do some nifty shits here like checking what operators to choose;
			$filter_vals = array();
			reset($filter_operators);
			foreach ($fields_placeholders as $field => $val)
			{
				foreach ($val as $placeholder)
				{
					array_push($filter_vals, sprintf(
						Statement\FILTER_KWORDS[current($filter_operators)] ?? Statement\FILTER_KWORDS['eq'],
						$field,
						$placeholder
					));
					next($filter_operators);
				}
			}

			$filter_phrase = $this->build_filter($this->phrase_filter_struct ?? NULL, $filter_vals);

			$this->stmnt .= sprintf(
				Statement\CLAUSES['WHERE'],
				$filter_phrase,
			);

			unset($fields_placeholders, $fields_placeholders, $filter_vals);
			$this->set_params("pos", ...$fields_filters);

			unset($filter_operators, $filter_phrase, $real_fields, $field, $val);
			return $this;
		}

		protected function write_values(string | int | float | bool ...$entries_values): QueryFactory
		{
			$values_placeholders = $this->placeholder_makr($entries_values, "add");
			$this->stmnt .= sprintf(
				Statement\CLAUSES['VALUES'],
				implode(", ", $values_placeholders)
			);
			unset($stmt_values);
			$this->set_params("add", ...$entries_values);
			return $this;
		}

		private function build_filter(?string $filter_struct, array $filter_vals): string // Add logic gates "OR" & "AND" to the filter phrase; // Remember to make descriptive logic gates, with 'intuitive' pharentheses wrapping n' shits;
		{ // It would just not work properly if you put two or more logic operators between a wrapped expression like (||) & & (&); The "AND" (&) in the middle of the given expression would not be added adjecent on the END and NEAR by '%s';
			if (!$filter_struct)
				return implode(" and ", $filter_vals);
			$farther = null;
			$revkey = null;
			$char = null;
			if (preg_replace("/(&|\|\||\(|\)|\s)*/", "", $filter_struct))
				throw new Exception("The valid characters are \"||\" and \"&\". Please look again to your argument.");
			$filter_struct = preg_replace("/( )/", "", $filter_struct);
			$revstr = strrev($filter_struct);
			if (substr_count($filter_struct, "(") != substr_count($filter_struct, ")"))
				throw new Exception("The pharentheses are not properly wrapping expressions!");
			$logic_chain_arr = str_split($filter_struct);
			for ($key = 0; $key < count($logic_chain_arr); $key++)
			{
				if (($char = $logic_chain_arr[$key]) == "|" || $char == "&")
				{
					if ($char == "|")
					{
						array_splice($logic_chain_arr, $key, 1);
						$filter_struct = substr_replace($filter_struct, "", $key, 1);
						$revstr = strrev($filter_struct);
					}
					$logic_chain_arr[$key] = "";
					$revkey = strlen($filter_struct) - ($key);
					$logic_chain_arr[$key] .=
						((!$farther = strpos($revstr, ")", $revkey)) || $farther > strpos($revstr, "(", $revkey) ? "%s" : "")
						. Statement\FILTER_LGATES[$char]
						. ((!$farther = strpos($filter_struct, "(", $key)) || $farther > strpos($filter_struct, ")", $key) ? "%s" : "");
				}
			}
			$filter_phrase = preg_replace("/(  )/", " ", preg_replace("/(%s%s)/", "%s", implode("", $logic_chain_arr)));

			if ((count($filter_vals) - 1) != substr_count($filter_struct, "|") + substr_count($filter_struct, "&"))
				throw new Exception("The count of given filter arguments are higher than the supposed lower lgates by 1.");

			$filter_phrase = (sprintf($filter_phrase, ...$filter_vals));

			unset($farther, $revstr, $revlen, $logic_chain_arr, $char);
			return $filter_phrase;
		}

		private const SQL_KEYWORD_ARG_FILTERS = [
			'int' => FILTER_SANITIZE_NUMBER_INT,
			'str' => FILTER_SANITIZE_ENCODED,
		];

		protected function write_kwords(?array $adjn_kwords): QueryFactory // key-word args "ORDER BY", "LIMIT", Etc.
		{
			if (empty($adjn_kwords)) return $this;
			$keyed_kwords = array_keys(Statement\KWORDS);
			$columns_real = $this->table_columns_real();
			$ordered_args = [];
			foreach ($adjn_kwords as $name => $value)
				if (in_array($name, $keyed_kwords))
				{
					$adjn_kwords[$name] = filter_var($value, self::SQL_KEYWORD_ARG_FILTERS[Statement\KWORDS[$name][1]]);
					$ordered_args[$name] = Statement\KWORDS[$name][2];
					$value = $this->fields_tokenized_realized_pairs()[$value] ?? $value;
					$adjn_kwords[$name] = $value;
					if (Statement\KWORDS[$name][3] && !in_array($value, $columns_real))
						throw new Exception("Invalid SQL Keyword supplied, {{$name}};");
				}
			unset($keyed_kwords);
			asort($ordered_args, SORT_NUMERIC);
			foreach ($adjn_kwords as $name => $value)
				$ordered_args[$name] = $value;
			foreach ($ordered_args as $name => $value)
				$this->stmnt .= sprintf(
					Statement\KWORDS[$name][0],
					$value
				);
			if (count($left_args = array_diff($adjn_kwords, $ordered_args)))
				throw new Exception("Invalid SQL Keywords is at large. " . implode(" | ", $left_args));
			unset($name, $value, $left_args, $ordered_args, $kw_arg_paramed);
			return $this;
		}

		function resolve(): Sense\QuerySet | NULL
		{
			if (!$this->prepare())
				throw new Exception("Database Query Mess UP: Prepared Statements Failed.");
			$set = new Sense\QuerySet($this->stmnt, $this);
			$this->reset();
			return $set->length ? $set : NULL;
		}

		function prepare(): bool
		{
			$this->stmnt = self::$CONN->prepare($this->stmnt . ";");
			$success = (bool) $this->stmnt;
			foreach ($this->stmnt_params['args-fields'] as $key => $val)
				if (!$this->stmnt->bindParam($val, $this->stmnt_params['args-values'][$key], $this->stmnt_params['args-types'][$key]))
					$success = false;

			unset($key, $val);
			return $success;
		}

		protected function set_params(string $prefix, string | int | bool | float | null ...$arguments): QueryFactory
		{
			array_push($this->stmnt_params['args-fields'], ...array_values($this->placeholder_makr($arguments, $prefix)));
			array_push($this->stmnt_params['args-types'], ...array_values($this->paramtype_evluatr($arguments)));
			array_push($this->stmnt_params['args-values'], ...array_values($arguments));
			return $this;
		}

		public function reset()
		{
			unset($this->stmnt, $this->phrase_filter_struct);
			$this->stmnt_params['args-fields'] = array();
			$this->stmnt_params['args-values'] = array();
			$this->stmnt_params['args-types'] = array();
			return $this;
		}

		private function placeholder_makr(array $target_fields, string $prefix_marker = "", bool $group = false)
		{
			$real_model = $this->table_columns_real();
			$id = 0;
			if ($group) // Made this instead so that the loop don't check every iteration;
			{
				foreach ($target_fields as $name => $value)
				{
					$field = explode("__", $name);
					array_pop($field);
					$target_fields[$field = implode("__", $field)] ??= array();
					if (!in_array($field, $real_model))
						throw new Exception("The field {{$field}} is not valid.");
					else
					{
						array_push($target_fields[$field], ":" . $prefix_marker . $name . $id++);
						unset($target_fields[$name]);
					}
				}
				unset($key, $val, $prefix_marker);
			}
			else
			{
				foreach ($target_fields as $name => $value)
				{
					$field = explode("__", $name);
					if (count($field) != 1) array_pop($field);
					if (!in_array($field = implode("__", $field), $real_model))
						throw new Exception("The field {{$field}} is not valid.");
					else
					{
						$target_fields[$name] = ":" . $prefix_marker . $name . $id++;
					}
				}
			}
			return $target_fields;
		}

		private const PARAM_TYPE_LOOKUP = [
			'string' => \PDO::PARAM_STR,
			'integer' => \PDO::PARAM_INT,
			'NULL' => \PDO::PARAM_NULL,
			'boolean' => \PDO::PARAM_BOOL,
		];

		// We don't need to do field parallelizing shits, those fancy shits.
		// There's no point. The value of each fields are already filtered to their
		// desired data type. So in this case, I would just gettype() it and do a lookup
		// to the right type label for the PDO::bindParam().
		private function paramtype_evluatr(array $target_fields)
		{
			foreach ($target_fields as $key => $val)
				$target_fields[$key] = self::PARAM_TYPE_LOOKUP[gettype($val)];
			unset($key, $val);
			return $target_fields;
		}

		static function init()
		{
			if (isset(self::$CONN)) return;
			self::$CONN = \Prefabs\Schema::attach();
		}

		static function the_schema(): \PDO
		{
			return self::$CONN;
		}

		public function the_stmnt()
		{
			return $this->stmnt;
		}
	}
};

namespace Query\Routines
{ // Builder Routine settings or mode
	const SELECT = 01;
	const UPDATE = 21;
	const DELETE = 31;
	const INSERT = 41;
}

namespace Query\Statement
{ // Predefined Strings
	const ROUTINES = [
		\Query\Routines\SELECT => "SELECT %s FROM %s",
		\Query\Routines\INSERT => "INSERT INTO %s (%s)",
		\Query\Routines\UPDATE => "UPDATE %s SET %s",
		\Query\Routines\DELETE => "DELETE FROM %s"
	];
	const CLAUSES = [
		'WHERE' => " WHERE %s",
		'VALUES' => " VALUES (%s)"
	];
	const CHARS = [
		'SELECT_ALL_FIELDS' => "*",
	];
	// What have I done? 1st: main content, 2nd: supposed dataType, 3rd: sort number, 
	// 4th: Boolean whether it's param should match to a table field (Fucking kill me).
	const KWORDS = [
		'order_by_desc' => [' ORDER BY `%s` DESC', 'str', 1, true],
		'order_by_asc' => [' ORDER BY `%s` ASC', 'str', 2, true],
		'limit' => [' LIMIT %s', 'int', 3, false],
	];
	const FILTER_KWORDS = [
		'not' => "NOT `%s` = %s",
		'eq' => "`%s` = %s",
		'lte' => "`%s` <= %s",
		'gte' => "`%s` >= %s",
		'lt' => "`%s` < %s",
		'gt' => "`%s` > %s",
		'like' => "`%s` LIKE %s",
	];
	const FILTER_LGATES = [
		'||' => " OR ",
		'|' => " OR ",
		'&' => " AND ",
	];
}

namespace Query\Statement\Functions
{
	function MAX(string $field_name)
	{
		return "MAX($field_name)";
	}
}
