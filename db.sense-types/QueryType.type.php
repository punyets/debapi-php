<?

namespace Query\Sense;

// The query_object and api accessor;
#[\AllowDynamicProperties]
class QueryType
{ // Row Result from Query Container
	const EXISTENCE_NEW = 0;
	const EXISTENCE_EXISTS = 1;

	private \Prefabs\TableAdit $adit;
	private bool $saved, $readonly;

	private array $row_content = [], $init_row_content = [];

	function __construct(array $row_iterable, \Prefabs\TableAdit &$tableAdit, int $existence = QueryType::EXISTENCE_EXISTS | QueryType::EXISTENCE_NEW)
	{
		$this->saved = $existence;
		$this->adit = $tableAdit;
		$table_model = $this->adit->table_model_token();
		$table_model_real = $this->adit->table_model_real();
		$target_field = null;
		reset($row_iterable);
		reset($table_model_real);
		do
		{
			$target_field = ($table_model[key($row_iterable)] ?? $table_model_real[key($row_iterable)]);
			$this->{key($row_iterable)} = current($row_iterable);
			unset($table_model_real[$target_field->get_field_name()]);
			next($row_iterable);
		} while (key($row_iterable) != null);
		if (!$this->saved)
			do
			{
				if (is_subclass_of(current($table_model_real), '\Prefabs\TableField\ChronoTypeField') && (current($table_model_real))->is_auto_now_add())
					$this->{key($table_model_real)} = current($table_model_real)->now();
				else
					$this->{key($table_model_real)} = current($table_model_real)->get_default();
				unset($table_model_real[key($table_model_real)]);
			} while (key($table_model_real) != null);

		$this->readonly = $this->saved && !empty($table_model_real);
		unset($table_model, $table_model_real, $target_field);
	}

	final function to_array(): array
	{
		$array = get_object_vars($this);
		unset($array['adit'], $array['saved'], $array['readonly'], $array['row_content'], $array['init_row_content']);
		return $array;
	}

	final function is_new(string $field_name)
	{
		return $this->row_content[$field_name]->out() !== $this->init_row_content[$field_name]->out();
	}

	final function fields(): array
	{
		return $this->row_content;
	}

	final function name()
	{
		return $this->adit->table_name();
	}

	// If you would like to change the row index, the pk, that's what I meant...
	static function swap_index(QueryType &$instance, int $primary_key): QueryType
	{
		$destination = $instance->adit->get($primary_key);
		$pk_token = $instance->adit->table_pk_name_token();
		$pk_field = $instance->adit->table_pk_name_real();
		$old_pk = $instance->init_row_content[$pk_token]->out();
		if (empty($destination))
		{ // IF TARGET ROW DON'T EXIST;
			// Just change the PK;
			$instance->adit->write_update(...[$pk_field => $primary_key])->write_where(...[$pk_field => $old_pk]);
			$instance->adit->prepare();
			$instance->adit->the_stmnt()->execute();
			$instance->adit->reset();

			// Hard reassignment of properties;
			unset($instance->{$pk_token});
			$instance->{$pk_token} = $primary_key;
		}
		else
		{ // TARGET ROW EXISTS SWAP THE TWO;
			// Remove the guy that has the target pk to avoid conflict of PK sameness;
			$destination->omit();

			// Do the same shit of updating only the PK;
			$instance->adit->write_update(...[$pk_field => $primary_key])->write_where(...[$pk_field => $old_pk]);
			$instance->adit->prepare();
			$instance->adit->the_stmnt()->execute();
			$instance->adit->reset();

			// Hard reassignment of properties;
			unset($instance->{$pk_token}, $destination->{$pk_token});
			$destination->{$pk_token} = $old_pk;
			$instance->{$pk_token} = $primary_key;

			// Create a new row for the deleted one;
			$destination->save();
		}
		// Some garbage clean up;
		unset($destination, $pk_field, $pk_token, $pk_field, $old_pk);

		// Do that in-routine save;
		return $instance->save();
	}

	// Just for synchronizing the properties to the "row_content" property
	private static function align_dossier(QueryType &$instance)
	{
		$table_model = $instance->adit->table_model_token(); // Get the tokenized key pairs. 
		do
		{
			$instance->{key($table_model)} = $instance->row_content[key($table_model)]->compose($instance->{key($table_model)});
			next($table_model);
		} while (key($table_model) != null);

		unset($table_model);
	}

	final function save(): QueryType
	{
		if ($this->readonly)
			throw new \Exception("Cannot save an Incomplete Instance");

		// Sync the object properties to the main_content assoc array;
		self::align_dossier($this);

		// Detect if the object about to be saved has the pk field changed;
		// If true then handle it by either swapping shit or directly putting it.
		if ($this->is_new($pk_field = $this->adit->table_pk_name_token()))
			return self::swap_index($this, $this->{$pk_field});

		unset($pk_field);

		$entry = NULL;


		if ($this->saved)
		{ // If saved then just update it;
			$this->row_content = array_merge( // Merge the fields to the main value content.
				$this->row_content,
				// Extract chrono-esque fields that has auto_now = true;
				// That means every save if not changed, then always update the chrono-esque field
				// to latest.
				array_map(
					function (\Prefabs\TableField\ChronoTypeField $field)
					{
						$field->compose($field->now());
						return $field;
					},
					array_filter(
						$this->row_content,
						fn ($val, $field) => (is_subclass_of($val, '\Prefabs\TableField\ChronoTypeField') && $val->is_auto_now() && !$this->is_new($field)),
						ARRAY_FILTER_USE_BOTH
					)
				)
			);
			unset($val, $field);
			$entry = $this->adit->edit($this)->to_array();
		}
		else
		{ // Create a new entry;
			$entry = $this->adit->save($this)->to_array();
			$this->saved = self::EXISTENCE_EXISTS;
		}
		do
		{
			unset($this->{key($entry)});
			$this->{key($entry)} = current($entry);
			next($entry);
		} while (key($entry) != null);
		return $this;
	}

	final function omit()
	{
		if ($this->readonly)
			throw new \Exception("Cannot delete an Incomplete Instance");
		$this->adit->omit($this);
		$this->saved = self::EXISTENCE_NEW;
		return $this;
	}

	final function __set(string $new_prop, $new_val)
	{
		$prop_name = $this->adit->fields_realized_tokenized_pairs()[$new_prop] ?? $new_prop;
		$target_field = $this->adit->table_model_token()[$prop_name];
		$target_field = clone $target_field;
		$target_field->compose($new_val);
		$this->row_content[$prop_name] = $target_field;
		if (!isset($this->readonly))
			$this->init_row_content[$prop_name] = clone $target_field;

		return $this->{$prop_name} = $this->row_content[$prop_name]->out();
	}

	final function __get($prop_name)
	{
		$prop_name = $this->adit->fields_realized_tokenized_pairs()[$prop_name] ?? $prop_name;
		return ($this->row_content[$prop_name] ?? NULL)?->out();
	}

	final function is_saved()
	{
		return $this->saved;
	}

	final function __serialize(): array
	{
		return $this->to_array();
	}

	final function __toString()
	{
		return json_encode($this->to_array());
	}
}
