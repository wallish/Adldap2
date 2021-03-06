<?php

namespace Adldap\Connections;

use Adldap\Auth\Guard;
use Adldap\Contracts\Auth\GuardInterface;
use Adldap\Contracts\Connections\ConnectionInterface;
use Adldap\Contracts\Connections\ProviderInterface;
use Adldap\Contracts\Schemas\SchemaInterface;
use Adldap\Exceptions\ConnectionException;
use Adldap\Models\Factory as ModelFactory;
use Adldap\Schemas\Schema;
use Adldap\Search\Factory as SearchFactory;
use InvalidArgumentException;

class Provider implements ProviderInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var Configuration
     */
    protected $configuration;

    /**
     * @var SchemaInterface
     */
    protected $schema;

    /**
     * @var GuardInterface
     */
    protected $guard;

    /**
     * {@inheritdoc}
     */
    public function __construct($configuration = [], ConnectionInterface $connection = null, SchemaInterface $schema = null)
    {
        $this->setConfiguration($configuration);
        $this->setConnection($connection);
        $this->setSchema($schema);
    }

    /**
     * {@inheritdoc}
     */
    public function __destruct()
    {
        if ($this->connection instanceof ConnectionInterface && $this->connection->isBound()) {
            $this->connection->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getGuard()
    {
        if (!$this->guard instanceof GuardInterface) {
            $this->setGuard($this->getDefaultGuard($this->connection, $this->configuration));
        }

        return $this->guard;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultGuard(ConnectionInterface $connection, Configuration $configuration)
    {
        return new Guard($connection, $configuration);
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection($connection = null)
    {
        if (is_null($connection)) {
            // Create a default LDAP connection if one isn't given.
            $connection = new Ldap();
        } elseif (!$connection instanceof ConnectionInterface) {
            $class = Configuration::class;

            throw new InvalidArgumentException("Connection must be an instance of $class");
        }

        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration($configuration = [])
    {
        if (is_array($configuration)) {
            // Construct a configuration instance if an array is given.
            $configuration = new Configuration($configuration);
        } elseif (!$configuration instanceof Configuration) {
            $class = Configuration::class;

            throw new InvalidArgumentException("Configuration must be either an array or instance of $class");
        }

        $this->configuration = $configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function setSchema($schema = null)
    {
        if (is_null($schema)) {
            // Retrieve the default schema if one isn't given.
            $schema = Schema::getDefault();
        } elseif (!$schema instanceof SchemaInterface) {
            $class = SchemaInterface::class;

            throw new InvalidArgumentException("Schema must be an instance of $class");
        }

        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * {@inheritdoc}
     */
    public function setGuard(GuardInterface $guard)
    {
        $this->guard = $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDse()
    {
        return $this->search()
            ->setDn(null)
            ->read(true)
            ->whereHas($this->schema->objectClass())
            ->first();
    }

    /**
     * {@inheritdoc}
     */
    public function make()
    {
        return new ModelFactory($this->search()->getQuery(), $this->schema);
    }

    /**
     * {@inheritdoc}
     */
    public function search()
    {
        return new SearchFactory($this->connection, $this->schema, $this->configuration->getBaseDn());
    }

    /**
     * {@inheritdoc}
     */
    public function connect($username = null, $password = null)
    {
        // Prepare the connection.
        $this->prepareConnection();

        // Retrieve the domain controllers.
        $controllers = $this->configuration->getDomainControllers();

        // Retrieve the port we'll be connecting to.
        $port = $this->configuration->getPort();

        // Connect to the LDAP server.
        if ($this->connection->connect($controllers, $port)) {
            $followReferrals = $this->configuration->getFollowReferrals();

            // Set the LDAP options.
            $this->connection->setOption(LDAP_OPT_PROTOCOL_VERSION, 3);
            $this->connection->setOption(LDAP_OPT_REFERRALS, $followReferrals);

            // Get the default guard instance.
            $guard = $this->getGuard();

            if (is_null($username) && is_null($password)) {
                // If both the username and password are null, we'll connect to the server
                // using the configured administrator username and password.
                $guard->bindAsAdministrator();
            } else {
                // Bind to the server with the specified username and password otherwise.
                $guard->bind($username, $password);
            }
        } else {
            throw new ConnectionException('Unable to connect to LDAP server.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function auth()
    {
        // Make sure the connection we've been given
        // is bound before we try to binding to it.
        if (!$this->connection->isBound()) {
            throw new ConnectionException('No connection to an LDAP server is present.');
        }

        return $this->getGuard();
    }

    /**
     * Prepares the connection by setting configured parameters.
     *
     * @return void
     */
    protected function prepareConnection()
    {
        // Set the beginning protocol options on the connection
        // if they're set in the configuration.
        if ($this->configuration->getUseSSL()) {
            $this->connection->useSSL();
        } elseif ($this->configuration->getUseTLS()) {
            $this->connection->useTLS();
        }
    }
}
