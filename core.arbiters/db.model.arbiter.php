<?

namespace Query;
// A class for handling or holding the definition of the Table Model made by trait Table;
// This thing defines a Table;
class TableModel
{
	protected $Model = [
		'fields' => [],
		'tablename' => '',
		'pk-field' => '',
	];

	private $Fields = [
		'pk-field-token' => null, # name of the primary key field as it was defined by a user
		'fields-real' => null, # name of the primary key field as it was in the table
		'columns-token' => null, # array of names of all the fields of the table as it was defined by the user
		'columns-real' => null, # array of names of all the fields of the table as it was in the table
		'pairs' => [
			'tokenized_realized' => null, # pairings
			'realized_tokenized' => null,
		]
	];

	# ommited (since the Table class in the JS Translation does half of this already)
	protected function set_model(string $tablename, array $tablefields): QueryFactory
	{
		$this->Model['tablename'] = $tablename;
		$this->Model['fields'] = $tablefields;
		do
		{
			if (current($this->Model['fields'])->get_field_type() == \Prefabs\TableField\PRIMARY_KEY)
			{
				$this->Model['pk-field'] = current($this->Model['fields'])->get_field_name();
				break;
			}
		} while (key($this->Model['fields']) != null);
		return $this;
	}

	# became pk_field
	public function table_pk_name_real()
	{
		return $this->Model['pk-field'];
	}

	# ommited
	public function table_pk_name_token()
	{
		return $this->Fields['pk-field-token'] ??= (function ()
		{
			do
			{
				if (current($this->Model['fields'])->get_field_type() == \Prefabs\TableField\PRIMARY_KEY)
					return key($this->Model['fields']);
			} while (key($this->Model['fields']) != null);
		})();
	}

	# became tablefields()
	public function table_model_token()
	{
		return ($this->Model['fields']);
	}

	# ommited
	public function table_model_real()
	{
		return $this->Fields['fields-real'] ??= $this->realize_field_names(...$this->Model['fields']);
	}

	# ommited
	public function table_columns_token()
	{
		return $this->Fields['columns-token'] ??= array_keys($this->table_model_token());
	}

	# became fieldnames()
	public function table_columns_real()
	{
		return $this->Fields['columns-real'] ??= array_keys($this->table_model_real());
	}

	# became tablename()
	public function table_name()
	{
		return $this->Model['tablename'];
	}

	# ommited
	protected function realize_field_names(\Prefabs\TableField\BaseField | string | null ...$fields_in): array
	{
		$model_base = $this->table_model_token();
		$fields_out = [];
		do
		{
			$fields_out[$model_base[key($fields_in)]->get_field_name()] = current($fields_in);
			next($fields_in);
		} while (key($fields_in) != null);
		unset($model_base, $fields_in);
		return $fields_out;
	}

	# ommited
	public function fields_realized_tokenized_pairs(): array
	{
		return $this->Fields['pairs']['realized_tokenized'] ??= (function ()
		{
			$fields = [];
			$model_base = $this->fields_tokenized_realized_pairs();
			reset($model_base);
			do
			{
				$fields[current($model_base)] = key($model_base);
				next($model_base);
			} while (key($model_base) != null);
			return $fields;
		})();
	}

	# ommited
	public function fields_tokenized_realized_pairs(): array
	{
		return $this->Fields['pairs']['tokenized_realized'] ??= (function ()
		{
			$fields = $this->table_model_token();
			do
			{
				$fields[key($fields)] = current($fields)->get_field_name();
				next($fields);
			} while (key($fields) != null);
			return $fields;
		})();
	}
}
