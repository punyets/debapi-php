<?
// There was a lot of omissions... The thought of doing it was through the realisation that the TableFields 
// is better suited as a descriptor rather than a structure for data.

namespace Prefabs\TableField
{ // Fields for handling datatypes, things like default values and shits.
	// TODO:
	// In the future, implement relational fields.
	use DateTime as GlobalDateTime;

	const PRIMARY_KEY = 011;
	const INTEGER = 012;
	const TEXT = 013;
	const BOOLEAN = 014;
	const DATETIME = 015;
	const TIME = 016;
	const DATE = 017;

	class BaseField
	{
		protected string $field_name, $type;
		protected int $name;
		protected mixed $default = NULL;
		protected bool $isNull, $autoFill;

		protected mixed $sense;

		function __construct(string $field_name, bool $null = false, $default = NULL)
		{
			$this->isNull = $null;
			$this->autoFill = isset($default);
			$this->default = $default;
			$this->field_name = $field_name;
		}

		function compose($data)
		{
			$this->autoFill = is_null($data); // If content is nothing then put the autofill to true
			return $this->sense = $this->autoFill ? $this->default : $data;
		} // Autofill is just a thing whether to put the default value or not

		function out()
		{
			return $this->sense;
		}

		final function get_field_type()
		{
			return $this->name;
		}

		final function get_field_name()
		{
			return $this->field_name;
		}

		final function get_datatype()
		{
			return $this->type;
		}

		function get_default()
		{
			return $this->default;
		}

		final function is_autofill()
		{
			return $this->autoFill;
		}

		final static function throw_datatype_unsame(BaseField $instance, int $line)
		{
			throw new \Exception('field value argument for ' . $instance::class . '::compose() not same type for.' . $instance::class . '::type property');
		}
	};
	class PrimaryKey extends BaseField
	{
		protected string $type = "INT", $char_eval = "i";
		protected int $name = PRIMARY_KEY;

		function __construct(string $field_name, bool $null = false, bool $auto_increment = true)
		{
			parent::__construct(field_name: $field_name, null: $null, default: null);
			$this->autoFill = $auto_increment;
		}

		function compose($pk_val)
		{
			if (is_null($pk_val)) return parent::compose($pk_val);

			return parent::compose((int) $pk_val);
		}
	}

	class Integer extends BaseField
	{
		protected string  $type = "INT";
		protected int $name = INTEGER;

		function __construct(string $field_name, bool $null = false, int $default = NULL)
		{
			parent::__construct(field_name: $field_name, null: $null, default: $default);
		}

		function compose($field_val)
		{
			if (is_null($field_val)) return parent::compose($field_val);

			return parent::compose((int) $field_val);
		}
	}

	class Text extends BaseField
	{
		protected string $type = "VARCHAR";
		protected int $name = TEXT;

		function __construct(string $field_name, bool $null = false, string $default = NULL)
		{
			parent::__construct(field_name: $field_name, null: $null, default: $default);
		}

		function compose($field_val)
		{
			if (empty($field_val)) return parent::compose($field_val);

			return parent::compose(strval($field_val));
		}

		function out()
		{
			return $this->sense;
		}
	}

	class Boolean extends BaseField
	{
		protected string $type = "BOOLEAN";
		protected int $name = BOOLEAN;

		function __construct(string $field_name, bool $null = false, bool $default = NULL)
		{
			parent::__construct(field_name: $field_name, null: $null, default: $default);
		}

		function compose($field_val)
		{
			if (is_null($field_val)) return parent::compose($field_val);

			return parent::compose(filter_var($field_val, FILTER_VALIDATE_BOOLEAN));
		}
	}


	// Time related fields
	abstract class ChronoTypeField extends BaseField
	{
		protected bool $auto_now = false, $auto_now_add = false;

		// IMPLEMENT THE FUNCTIONALITY OF AUTO_NOW AND AUTO_NOW_ADD
		function __construct(string $field_name, bool $auto_now = false, bool $auto_now_add = false)
		{
			$this->isNull = (!$auto_now && !$auto_now_add);
			$this->autoFill = ($auto_now || $auto_now_add);
			$this->field_name = $field_name;
			$this->auto_now = $auto_now; // always update the field every time you save
			$this->auto_now_add = $auto_now_add;
		}

		abstract function now(): string;

		function is_auto_now()
		{
			return $this->auto_now;
		}

		function is_auto_now_add()
		{
			return $this->auto_now_add;
		}
	}

	class DateTime extends ChronoTypeField
	{
		protected string $type = "DATETIME";
		protected int $name = DATETIME;

		function compose(...$date_time_assoc)
		{
			$this->autoFill = empty($date_time_assoc);

			if (!(count($date_time_assoc) > 1) && is_string($date_time_assoc[0]))
			{
				if (!is_string($date_time_assoc[0]))
					self::throw_datatype_unsame($this, __LINE__);
				[$date_time_assoc['date'], $date_time_assoc['time']] = explode(" ", $date_time_assoc[0]);
				unset($date_time_assoc[0]);
			}
			else
				$date_time_assoc = $date_time_assoc[0];

			if (empty($date_time_assoc))
				return $this->sense = $this->get_default();

			if (!($date_time_assoc['date']) && !($date_time_assoc['time']))
				self::throw_datatype_unsame($this, __LINE__);

			[$year, $month, $day] = explode("-", $date_time_assoc['date']);
			[$hour, $minute, $second] = explode(":", $date_time_assoc['time']);
			$this->sense = (new GlobalDateTime())->setDate($year, $month, $day)->setTime($hour, $minute, $second);
			unset($year, $month, $day, $hour, $minute, $second);
			return $this->out();
		}

		function out()
		{
			return empty($this->sense) ? $this->sense : $this->sense->format("Y-m-d H:i:s");
		}

		function now(): string
		{
			return date("Y-m-d H:i:s");
		}
	}

	class Time extends ChronoTypeField
	{
		protected string $type = "TIME";
		protected int $name = TIME;

		function compose($time)
		{
			$this->autoFill = empty($time);
			if (empty($time))
				return $this->sense = $this->get_default();
			if (!is_string($time))
				self::throw_datatype_unsame($this, __LINE__);
			$this->sense = (new GlobalDateTime())->setTime(...[$hour, $minute, $second] = explode(":", $time));
			return $this->out();
		}

		function out()
		{
			return empty($this->sense) ? $this->sense : $this->sense->format("H:i:s");
		}

		function now(): string
		{
			return date("H:i:s");
		}
	}

	class Date extends ChronoTypeField
	{
		protected string $type = "DATE";
		protected int $name = DATE;

		function compose($date)
		{
			$this->autoFill = empty($date);
			if (empty($date))
				return $this->sense = $this->get_default();
			if (!is_string($date))
				self::throw_datatype_unsame($this, __LINE__);
			$this->sense = (new GlobalDateTime())->setDate(...[$year, $month, $day] = explode("-", $date));
			return $this->out();
		}

		function out()
		{
			return empty($this->sense) ? $this->sense : $this->sense->format("Y-m-d");
		}

		function now(): string
		{
			return date("Y-m-d");
		}
	}
}

namespace
{

	use Prefabs\TableField;

	class Field
	{
		final static function PrimaryKey(string $name, bool $null = false, bool $auto_increment = true): TableField\PrimaryKey
		{
			return new TableField\PrimaryKey($name, $null, $auto_increment);
		}

		final static function Integer(string $name, bool $null = false, int $default = NULL): TableField\Integer
		{
			return new TableField\Integer($name, $null, $default);
		}

		final static function Text(string $name, bool $null = false, string $default = NULL): TableField\Text
		{
			return new TableField\Text($name, $null, $default);
		}

		final static function Boolean(string $name, bool $null = false, bool $default = NULL): TableField\Boolean
		{
			return new TableField\Boolean($name, $null, $default);
		}

		final static function DateTime(string $name, bool $auto_now = false, bool $auto_now_add = false): TableField\DateTime
		{
			return new TableField\DateTime($name, $auto_now, $auto_now_add);
		}

		final static function Time(string $name, bool $auto_now = false, bool $auto_now_add = false): TableField\Time
		{
			return new TableField\Time($name, $auto_now, $auto_now_add);
		}

		final static function Date(string $name, bool $auto_now = false, bool $auto_now_add = false): TableField\Date
		{
			return new TableField\Date($name, $auto_now, $auto_now_add);
		}
	}
}
