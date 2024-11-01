<?

namespace Query\Sense;

// TODO
// Implement bulk delete and update
class QuerySet implements \ArrayAccess, \Countable, \Iterator, \Stringable
{ // Array Like Object container for QueryType objects;
	protected array $sense = [], $modified_bulk = [];
	public array $details = [
		'num-rows' => 0,
		'field-count' => 0,
	];
	public int $length;

	protected \Prefabs\TableAdit $adit;

	protected ?array $causality = NULL;

	function __construct(\PDOStatement &$stmnt, \Prefabs\TableAdit $adit)
	{
		$stmnt->execute();
		$this->details['num-rows'] = $stmnt->rowCount();
		$this->length = $this->details['num-rows'];
		$this->details['field-count'] = $stmnt->columnCount();
		$this->adit = $adit;
		while ($row = $stmnt->fetch(\PDO::FETCH_ASSOC))
			$this->sense[] = new QueryType($row, $adit, QueryType::EXISTENCE_EXISTS);
	}

	function save(): QuerySet
	{
		$this->adit->bulk_edit($this);
		return $this;
	}

	function omit(): void
	{
		$this->adit->bulk_omit($this);
		$this->reset();
	}

	function out(): array
	{
		return array_map(
			fn ($val) => $val->to_array(),
			$this->sense,
		);
	}

	function &array(): array
	{
		return $this->sense;
	}

	function reset(): void
	{
		foreach (array_keys(get_object_vars($this)) as $prop_name)
			unset($this->{$prop_name});

		foreach (array_keys($this->sense) as $index)
			unset($this[$index]);

		unset($prop_name, $index);
	}

	function get_bulk_content(): array
	{
		return $this->modified_bulk;
	}

	function set_clause(array $filter, array $adjoin = []): QuerySet
	{
		$this->causality = [
			$filter,
			$adjoin
		];
		return $this;
	}

	function get_clause(): array
	{
		return $this->causality;
	}

	function __set($prop_name, $new_val)
	{
		if ($target_field = ($this->adit->table_model_token()[$prop_name] ?? $this->adit->table_model_real()[$prop_name]))
		{
			if ($target_field->get_field_type() == \Prefabs\TableField\PRIMARY_KEY)
				throw new \Exception("Cannot specify a primary key field! " . __CLASS__ . "::__construct");
			$target_field = clone $target_field;
			$target_field->compose($new_val);
			$this->modified_bulk[$prop_name] = $target_field;
			foreach (array_keys($this->sense) as $index)
				$this[$index]->{$prop_name} = $new_val;
		}
		else
			throw new \Exception("Cannot set not existing field in " . __CLASS__ . "::__set. Field $prop_name does not exist!");
	}

	function count() : int
	{
		return count($this->sense);
	}

	function &getIterator()
	{
		return $this->sense;
	}

	function offsetUnset($offset): void
	{
		unset($this->sense[$offset]);
	}

	function offsetExists($offset): bool
	{
		return isset($this->sense[$offset]);
	}

	function offsetSet($offset, $value): void
	{
		$this->sense[$offset] = $value;
	}

	function &offsetGet($offset): QueryType | array | null
	{
		return $this->sense[$offset];
	}

	function key(): ?int
	{
		return key($this->sense);
	}

	function current(): QueryType | array | bool
	{
		return current($this->sense);
	}

	function next(): void
	{
		next($this->sense);
	}

	function prev(): void
	{
		prev($this->sense);
	}

	function start(): QueryType | array | null | bool
	{
		return reset($this->sense);
	}

	function end(): QueryType | array | null | bool
	{
		return end($this->sense);
	}

	function rewind(): void
	{
		reset($this->sense);
	}

	function valid(): bool
	{
		return key($this->sense) !== null;
	}

	function __get($name)
	{
		return $this[$name];
	}

	final function __toString(): string
	{
		return json_encode($this->out());
	}

	function __serialize(): array
	{
		return $this->out();
	}
}
