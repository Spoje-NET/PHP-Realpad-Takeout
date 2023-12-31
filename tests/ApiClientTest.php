<?php

namespace Test\SpojeNet\Realpad;

use SpojeNet\Realpad\ApiClient;

/**
 * Generated by PHPUnit_SkeletonGenerator on 2023-10-10 at 15:16:24.
 */
class ApiClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ApiClient
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new ApiClient();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {

    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::curlInit
     */
    public function testcurlInit()
    {
        $this->assertIsObject($this->object->curlInit());
    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::doCurlRequest
     */
    public function testdoCurlRequest()
    {
        $this->assertEquals(200, $this->object->doCurlRequest('https://realpadsoftware.com/cs/'));
    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::xml2array
     */
    public function testxml2array()
    {
        $xml = '<note><to>Tove</to><from>Jani</from><heading>Reminder</heading><body>this weekend!</body></note>';
        $array = ['to' => 'Tove', 'from' => 'Jani', 'heading' => 'Reminder', 'body' => 'this weekend!'];
        $this->assertEquals($array, $this->object->xml2array(new \SimpleXMLElement($xml)));
    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::disconnect
     */
    public function testdisconnect()
    {
        $this->assertNull($this->object->disconnect());
    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::listResources
     */
    public function testlistResources()
    {
        $this->assertIsArray($this->object->listResources());
    }

    /**
     * @covers SpojeNet\Realpad\ApiClient::listCustomers
     */
    public function testlistCustomers()
    {
        $this->assertIsArray($this->object->listCustomers());
    }
    
    /**
     * @covers SpojeNet\Realpad\ApiClient::__destruct
     */
    public function test__destruct()
    {
        $this->assertEmpty($this->object->__destruct());
    }
}
