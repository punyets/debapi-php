<?
namespace Prefabs;
use PDO;
class Schema
{
	private string $database;

	private string $host, $port, $socket;

	private string $dsn;

	private string $username, $password;

	private array $options = [];

	private string $ssl_ca, $ssl_cert, $ssl_key, $ssl_ca_path, $ssl_cipher;

	private PDO $CONN;

	private static PDO $CURRENT_CONN;

	final function __construct(string $dbname, string $username, string $port = "", bool $is_unix=false, string $host = "", string $socket = "", string $password = "")
	{
		$this->database = $dbname;
		$this->host = $host;
		$this->port ??= $port;
		$this->socket ??= $socket;

		$this->username = $username;
		$this->password ??= $password;

		$this->dsn = "mysql:" .
			"dbname=$this->database;" .
            (
                $is_unix ? 
                "unix_socket=$this->socket" : 
                "host=$this->host;" .
                ($this->port ? "port=$this->port;" : "") .
                ($this->socket ? "socket=$this->socket;" : "")
            );

		return $this;
	}

	final function ssl(string $cert_auth, string $cert, string $key, string $all_ca_path = "", string $cipher = "")
	{
		$this->ssl_ca = $cert_auth;
		$this->ssl_cert = $cert;
		$this->ssl_key = $key;
		$this->ssl_ca_path = $all_ca_path;
		$this->ssl_cipher = $cipher;

		$this->options += [
			PDO::MYSQL_ATTR_SSL_CA => $this->ssl_ca,
			PDO::MYSQL_ATTR_SSL_CERT => $this->ssl_cert,
			PDO::MYSQL_ATTR_SSL_KEY => $this->ssl_key,
		];

		if ($all_ca_path)
		{
			$this->options[PDO::MYSQL_ATTR_SSL_CAPATH] = $this->ssl_ca_path;;
		}

		if ($cipher)
		{
			$this->options[PDO::MYSQL_ATTR_SSL_CIPHER] = $this->ssl_cipher;
		}

		return $this;
	}

	final function init()
	{

		$this->CONN = new PDO(
			dsn: $this->dsn,
			username: $this->username,
			password: $this->password,
			options: $this->options
		);

		return $this->use();
	}

	final function use()
	{
		self::$CURRENT_CONN = $this->CONN;

		return $this;
	}

	final static function attach() : \PDO
	{
		return self::$CURRENT_CONN;
	}

	final static function Table(string $name, array $fields)
	{
		return (new Table($name, $fields, self::$CURRENT_CONN));
	}
}
