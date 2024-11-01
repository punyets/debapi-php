<?

namespace Prefabs;

require 'core.arbiters/db.query.arbiter.php';

use Exception;

class TableAdit extends \Query\QueryFactory
{ // Table Model Access API

	private array $stmnt_adjns, $stmnt_cols;

	public function __construct(string $Tablename, array $Fields)
	{
		$this->set_model($Tablename, $Fields);
	}

	public function all(): \Query\Sense\QuerySet | NULL
	{
		$this->write_select(...($this->stmnt_cols ?? $this->table_columns_real()));
		if (!empty($this->stmnt_adjns))
			$this->write_kwords($this->stmnt_adjns ?? NULL);
		unset($this->stmnt_cols, $this->stmnt_adjns);
		return $this->resolve() ?? NULL; // Fetch the query
	}

	public function filter(string | int | float | bool | null ...$field_query): ?\Query\Sense\QuerySet
	{
		$columns_token = $this->table_model_token();
		$columns_real = $this->table_model_real();
		$filtres_operators = array_keys(\Query\Statement\FILTER_KWORDS);
		$spcl_field = null;
		$rawed_filtres = [];

		// Validate the arguments;

		foreach ($field_query as $field => $val)
		{
			if (isset($columns_real[$field]) || isset($columns_token[$field])) // This means it's just a good ol' "=" operator;
			{
				unset($field_query[$field]);
				$rawed_filtres[$field . "__eq"] = $val; // make the regular as its supposed raw
				continue;
			}

			// If this is false then that means the check whether it rawed filter the regular filter
			// is not valid;
			$spcl_field = explode("__", $field);
			if (!(($spcl_field = array_pop($spcl_field)) && (in_array($spcl_field, $filtres_operators) || !empty((int)$spcl_field))))
				throw new Exception("Filter query field {{$field}} is not valid.");
		}

		unset($spcl_field, $filtres_operators);

		// If nothin bad happens like no throwing up, then append the already rawed shits from the start;
		$rawed_filtres = array_merge($rawed_filtres, array_diff_key($field_query, $rawed_filtres));

		// Realize the field names;

		$fname_rawed = [];

		// We need to make the field name version from the rawed.
		foreach (array_keys($rawed_filtres) as $field)
		{
			$field = explode("__", $field);
			array_pop($field);
			$fname_rawed[$field = implode("__", $field)] ??= array();
			array_push($fname_rawed[$field], key($rawed_filtres));
			next($rawed_filtres);
		}

		reset($rawed_filtres);

		// Why? So that it would work for realize_field_names(). I don't want to change the method anymore.
		// So I decided to just make the array as a json_encoded string;
		$fname_rawed = array_map(fn ($val) => json_encode($val), $fname_rawed);

		// Then realize it. Make it match the real one the one defined from the database;
		// You can either put the "tokenized" ones or the "real" version of the name of fields
		// And it always convert to the "real" ones;
		$fname_rawed = array_merge(
			// Haha WTF.
			// It just realizes the unrealized ones then just merge the realized ones.
			// array_merge() fits this perfectly as it handles the same keys and shits.
			// so it doesn't matter if it is same for both the tokenize and realized
			$this->realize_field_names(
				...array_intersect_key(
					$fname_rawed,
					$columns_token
				)
			),
			array_intersect_key(
				$fname_rawed,
				$columns_real
			)
		);

		$fname_rawed = array_map(fn ($val) => json_decode($val), $fname_rawed);

		// Now we put the realized field names to the main array, ($rawed_filtres)
		foreach ($fname_rawed as $field => $val)
			foreach ($val as $origl_field)
			{
				$field_new = $field . "__" . array_slice(explode("__", $origl_field), -1)[0];
				$val = $rawed_filtres[$origl_field];
				unset($rawed_filtres[$origl_field]);
				$rawed_filtres[$field_new] = $val;
			}

		reset($rawed_filtres);
		unset($field, $columns_real, $columns_token, $fname_rawed);

		// We have now paralleled the names to the legit ones.

		// Now just make the statement;
		$this->write_select(...($this->stmnt_cols ?? $this->table_columns_real()));
		$this->write_where(...$rawed_filtres);
		$this->write_kwords($this->stmnt_adjns ?? NULL);

		// Garbage cleanup;
		unset($field, $columns_token, $columns_real, $options, $this->stmnt_cols);

		// Get the query then Set the "where" clause. Useful for the CRUD functionality of QuerySet class. Bulk update or delete whatsoever;
		$set = $this->resolve()?->set_clause($rawed_filtres, $this->stmnt_adjns ?? []);

		unset($field_query, $adjs_holder, $rawed_filtres, $this->stmnt_adjns);

		return $set;
	}

	public function get(int $pk): \Query\Sense\QueryType | NULL
	{

		if (isset($this->cache_stmnt['get']))
		{
			$this->stmnt = $this->cache_stmnt['get'];
			$this->set_params("pos", ...[$this->Model['pk-field'] . "__eq" => $pk]);
		}
		else
		{
			$this->write_select(...($this->stmnt_cols ?? $this->table_columns_real()));
			$this->write_where(...[$this->Model['pk-field'] . "__eq" => $pk]);
			$this->cache_stmnt['get'] = $this->stmnt;
		}
		// Get only first and last result.
		return $this->resolve()[0] ?? NULL;
	}

	public function save(\Query\Sense\QueryType &$instance): \Query\Sense\QueryType
	{
		$main_fields = $this->realize_field_names(...array_filter(
			$instance->fields(),
			fn ($val) => !$val->is_autofill(),
		));

		$values = array_map(
			fn ($val) => $val->out(),
			$main_fields
		);

		if (isset($this->cache_stmnt['save']))
		{
			$this->stmnt = $this->cache_stmnt['save'];
			$this->set_params("add", ...$values);
		}
		else
		{
			if ($pk_value = $main_fields[$this->table_pk_name_real()] ?? NULL)
			{
				$target_pk = $this->get($pk_value->out());
				if (!is_null($target_pk))
					throw new \Exception("Cannot save entry. Primary Key - " . $pk_value->out() . ", already exist!");
			}
			$this->write_insert(...array_keys($main_fields));
			$this->write_values(...$values);
			if (!isset($pk_value))
				$this->cache_stmnt['save'] = $this->stmnt;
			unset($val, $main_fields);
		}
		$this->prepare();
		$this->stmnt->execute();
		$this->reset();
		return $this->get(self::the_schema()->lastInsertId());
	}

	public function edit(\Query\Sense\QueryType &$instance): \Query\Sense\QueryType
	{
		$pk_field = $this->table_pk_name_token();

		$entry = array_map(
			fn ($val) => $val->out(),
			$this->realize_field_names(...$instance->fields())
		);
		unset($val);

		if (isset($this->cache_stmnt['edit']))
		{
			$this->stmnt = $this->cache_stmnt['edit'];
			$this->set_params("edit", ...($entry));
			$this->set_params("pos", ...[$this->Model['pk-field'] . "__eq" => $instance->{$pk_field}]);
		}
		else
		{
			$this->write_update(...$entry);
			$this->write_where(...[$this->Model['pk-field'] . "__eq" => $instance->{$pk_field}]);
			$this->cache_stmnt['edit'] = $this->stmnt;
		}
		$this->prepare();
		$this->stmnt->execute();
		$this->reset();
		return $this->get($entry[$this->table_pk_name_real()]);
	}

	public function columns(string $target_cols): TableAdit
	{ // Specify the columns you want to return for all() and filter();
		if (substr_count($target_cols, "`") % 2)
			throw new Exception('The name wrappings for column labels are not valid');
		preg_match_all("/`(.*?)`/", preg_replace("/([\"\'])/", "`", $target_cols), $matches);
		$this->stmnt_cols = array_map(function ($val)
		{
			return $this->fields_tokenized_realized_pairs()[$val] ?? $val;
		}, $matches[1]);
		return $this;
	}

	public function adjoin(string | int | float ...$args): TableAdit
	{
		foreach ($args as $key => $kword)
			$this->stmnt_adjns[$key] = $kword;
		unset($args, $key, $kword);
		return $this;
	}

	function filter_struct(string $logic_struct): TableAdit // filter logic gates "OR" & "AND"; // Remember to make descriptive logic gates, with 'intuitive' pharentheses wrapping n' shits;
	{ // It would just not work properly if you put two or more logic operators between a wrapped expression like (||) & & (&); The "AND" (&) in the middle of the given expression would not be added adjecent on the END and NEAR by '%s';
		$this->phrase_filter_struct = $logic_struct;
		return $this;
	}

	public function bulk_edit(\Query\Sense\QuerySet &$instance): \Query\Sense\QuerySet
	{
		[$query_range, $adjn_kwords] = $instance->get_clause();

		$entry = array_map(
			fn ($val) => $val->out(),
			$this->realize_field_names(...$instance->get_bulk_content())
		);

		$this->write_update(...$entry);

		if ($query_range)
			$this->write_where(...$query_range);

		$this->prepare();
		$this->stmnt->execute();
		$this->reset();

		return $instance;
	}

	public function omit(\Query\Sense\QueryType &$instance): \Query\Sense\QueryType
	{
		$pk_field = $this->table_pk_name_token();
		if (isset($this->cache_stmnt['omit']))
		{
			$this->stmnt = $this->cache_stmnt['omit'];
			$this->set_params("pos", ...[$this->Model['pk-field'] . "__eq" => $instance->{$pk_field}]);
		}
		else
		{
			$this->write_delete();
			$this->write_where(...[$this->Model['pk-field'] . "__eq" => $instance->{$pk_field}]);
			$this->cache_stmnt['omit'] = $this->stmnt;
		}
		$this->prepare();
		$this->stmnt->execute();
		$this->reset();
		return $instance;
	}

	public function bulk_omit(\Query\Sense\QuerySet &$instance): \Query\Sense\QuerySet
	{
		[$query_range, $adjn_kwords] = $instance->get_clause();

		$this->write_delete();

		if ($query_range)
			$this->write_where(...$query_range);

		$this->prepare();
		$this->stmnt->execute();
		$this->reset();

		return $instance;
	}
};
