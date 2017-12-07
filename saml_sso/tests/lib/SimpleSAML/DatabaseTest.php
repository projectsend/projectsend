<?php


/**
 * This test ensures that the SimpleSAML_Database class can properly
 * query a database.
 *
 * It currently uses sqlite to test, but an alternate config.php file
 * should be created for test cases to ensure that it will work
 * in an environment.
 *
 * @author Tyler Antonio, University of Alberta. <tantonio@ualberta.ca>
 * @package SimpleSAMLphp
 */
class SimpleSAML_DatabaseTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var SimpleSAML_Configuration
     */
    protected $config;

    /**
     * @var SimpleSAML\Database
     */
    protected $db;


    /**
     * Make protected functions available for testing
     *
     * @param string $getMethod The method to get.
     * @requires PHP 5.3.2
     *
     * @return mixed The method itself.
     */
    protected static function getMethod($getMethod)
    {
        $class = new ReflectionClass('SimpleSAML\Database');
        $method = $class->getMethod($getMethod);
        $method->setAccessible(true);
        return $method;
    }


    /**
     * @covers SimpleSAML\Database::getInstance
     * @covers SimpleSAML\Database::generateInstanceId
     * @covers SimpleSAML\Database::__construct
     * @covers SimpleSAML\Database::connect
     */
    public function setUp()
    {
        $config = array(
            'database.dsn'        => 'sqlite::memory:',
            'database.username'   => null,
            'database.password'   => null,
            'database.prefix'     => 'phpunit_',
            'database.persistent' => true,
            'database.slaves'     => array(),
        );

        $this->config = new SimpleSAML_Configuration($config, "test/SimpleSAML/DatabaseTest.php");

        // Ensure that we have a functional configuration class
        $this->assertInstanceOf('SimpleSAML_Configuration', $this->config);
        $this->assertEquals($config['database.dsn'], $this->config->getString('database.dsn'));

        $this->db = SimpleSAML\Database::getInstance($this->config);

        // Ensure that we have a functional database class.
        $this->assertInstanceOf('SimpleSAML\Database', $this->db);
    }


    /**
     * @covers SimpleSAML\Database::getInstance
     * @covers SimpleSAML\Database::generateInstanceId
     * @covers SimpleSAML\Database::__construct
     * @covers SimpleSAML\Database::connect
     * @expectedException Exception
     * @test
     */
    public function connectionFailure()
    {
        $config = array(
            'database.dsn'        => 'mysql:host=localhost;dbname=saml',
            'database.username'   => 'notauser',
            'database.password'   => 'notausersinvalidpassword',
            'database.prefix'     => 'phpunit_',
            'database.persistent' => true,
            'database.slaves'     => array(),
        );

        $this->config = new SimpleSAML_Configuration($config, "test/SimpleSAML/DatabaseTest.php");
        $db = SimpleSAML\Database::getInstance($this->config);
    }


    /**
     * @covers SimpleSAML\Database::getInstance
     * @covers SimpleSAML\Database::generateInstanceId
     * @covers SimpleSAML\Database::__construct
     * @covers SimpleSAML\Database::connect
     * @test
     */
    public function instances()
    {
        $config = array(
            'database.dsn'        => 'sqlite::memory:',
            'database.username'   => null,
            'database.password'   => null,
            'database.prefix'     => 'phpunit_',
            'database.persistent' => true,
            'database.slaves'     => array(),
        );
        $config2 = array(
            'database.dsn'        => 'sqlite::memory:',
            'database.username'   => null,
            'database.password'   => null,
            'database.prefix'     => 'phpunit2_',
            'database.persistent' => true,
            'database.slaves'     => array(),
        );

        $config1 = new SimpleSAML_Configuration($config, "test/SimpleSAML/DatabaseTest.php");
        $config2 = new SimpleSAML_Configuration($config2, "test/SimpleSAML/DatabaseTest.php");
        $config3 = new SimpleSAML_Configuration($config, "test/SimpleSAML/DatabaseTest.php");

        $db1 = SimpleSAML\Database::getInstance($config1);
        $db2 = SimpleSAML\Database::getInstance($config2);
        $db3 = SimpleSAML\Database::getInstance($config3);

        $generateInstanceId = self::getMethod('generateInstanceId');

        $instance1 = $generateInstanceId->invokeArgs($db1, array($config1));
        $instance2 = $generateInstanceId->invokeArgs($db2, array($config2));
        $instance3 = $generateInstanceId->invokeArgs($db3, array($config3));

        // Assert that $instance1 and $instance2 have different instance ids
        $this->assertNotEquals(
            $instance1,
            $instance2,
            "Database instances should be different, but returned the same id"
        );
        // Assert that $instance1 and $instance3 have identical instance ids
        $this->assertEquals(
            $instance1,
            $instance3,
            "Database instances should have the same id, but returned different id"
        );

        // Assert that $db1 and $db2 are different instances
        $this->assertNotEquals(
            spl_object_hash($db1),
            spl_object_hash($db2),
            "Database instances should be different, but returned the same spl_object_hash"
        );
        // Assert that $db1 and $db3 are identical instances
        $this->assertEquals(
            spl_object_hash($db1),
            spl_object_hash($db3),
            "Database instances should be the same, but returned different spl_object_hash"
        );
    }


    /**
     * @covers SimpleSAML\Database::getInstance
     * @covers SimpleSAML\Database::generateInstanceId
     * @covers SimpleSAML\Database::__construct
     * @covers SimpleSAML\Database::connect
     * @covers SimpleSAML\Database::getSlave
     * @test
     */
    public function slaves()
    {
        $getSlave = self::getMethod('getSlave');

        $master = spl_object_hash(PHPUnit_Framework_Assert::readAttribute($this->db, 'dbMaster'));
        $slave = spl_object_hash($getSlave->invokeArgs($this->db, array()));

        $this->assertTrue(($master == $slave), "getSlave should have returned the master database object");

        $config = array(
            'database.dsn'        => 'sqlite::memory:',
            'database.username'   => null,
            'database.password'   => null,
            'database.prefix'     => 'phpunit_',
            'database.persistent' => true,
            'database.slaves'     => array(
                array(
                    'dsn'      => 'sqlite::memory:',
                    'username' => null,
                    'password' => null,
                ),
            ),
        );

        $sspConfiguration = new SimpleSAML_Configuration($config, "test/SimpleSAML/DatabaseTest.php");
        $msdb = SimpleSAML\Database::getInstance($sspConfiguration);

        $slaves = PHPUnit_Framework_Assert::readAttribute($msdb, 'dbSlaves');
        $gotSlave = spl_object_hash($getSlave->invokeArgs($msdb, array()));

        $this->assertEquals(
            spl_object_hash($slaves[0]),
            $gotSlave,
            "getSlave should have returned a slave database object"
        );
    }


    /**
     * @covers SimpleSAML\Database::applyPrefix
     * @test
     */
    public function prefix()
    {
        $prefix = $this->config->getString('database.prefix');
        $table = "saml20_idp_hosted";
        $pftable = $this->db->applyPrefix($table);

        $this->assertEquals($prefix.$table, $pftable, "Did not properly apply the table prefix");
    }


    /**
     * @covers SimpleSAML\Database::write
     * @covers SimpleSAML\Database::read
     * @covers SimpleSAML\Database::exec
     * @covers SimpleSAML\Database::query
     * @test
     */
    public function querying()
    {
        $table = $this->db->applyPrefix("sspdbt");
        $this->assertEquals($this->config->getString('database.prefix')."sspdbt", $table);

        $this->db->write(
            "CREATE TABLE IF NOT EXISTS $table (ssp_key INT(16) NOT NULL, ssp_value TEXT NOT NULL)",
            false
        );

        $query1 = $this->db->read("SELECT * FROM $table");
        $this->assertEquals(0, $query1->fetch(), "Table $table is not empty when it should be.");

        $ssp_key = time();
        $ssp_value = md5(rand(0, 10000));
        $stmt = $this->db->write(
            "INSERT INTO $table (ssp_key, ssp_value) VALUES (:ssp_key, :ssp_value)",
            array('ssp_key' => array($ssp_key, PDO::PARAM_INT), 'ssp_value' => $ssp_value)
        );
        $this->assertEquals(1, $stmt->rowCount(), "Could not insert data into $table.");

        $query2 = $this->db->read("SELECT * FROM $table WHERE ssp_key = :ssp_key", array('ssp_key' => $ssp_key));
        $data = $query2->fetch();
        $this->assertEquals($data['ssp_value'], $ssp_value, "Inserted data doesn't match what is in the database");
    }


    /**
     * @covers SimpleSAML\Database::read
     * @covers SimpleSAML\Database::query
     * @expectedException Exception
     * @test
     */
    public function readFailure()
    {
        $table = $this->db->applyPrefix("sspdbt");
        $this->assertEquals($this->config->getString('database.prefix')."sspdbt", $table);

        $this->db->read("SELECT * FROM $table");
    }


    /**
     * @covers SimpleSAML\Database::write
     * @covers SimpleSAML\Database::exec
     * @expectedException Exception
     * @test
     */
    public function noSuchTable()
    {
        $this->db->write("DROP TABLE phpunit_nonexistent", false);
    }


    public function tearDown()
    {
        $table = $this->db->applyPrefix("sspdbt");
        $this->db->write("DROP TABLE IF EXISTS $table", false);

        unset($this->config);
        unset($this->db);
    }
}
