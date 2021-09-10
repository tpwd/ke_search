<?php

class LibOrderingTest extends Tx_Extbase_BaseTestCase
{

    public $conf = array();


    public function setUp()
    {
        /** @var \Tpwd\KeSearch\Lib\PluginBaseHelper div */
        $this->div = new \Tpwd\KeSearch\Lib\PluginBaseHelper();
    }

    public function tearDown()
    {
        unset($this->div);
    }


    /**
     * Test ordering if no searchword was given
     * @test
     */
    public function checkOrderingWithoutNeededConditions()
    {
        // Test with showSortInFrontend = false
        $this->conf = array(
            'showSortInFrontend' => false,
            'sortByVisitor' => 'sortdate,title,tstamp',
        );
        $this->numberOfResults = 35;

        /** @var \Tpwd\KeSearch\Lib\Pluginbase $lib */
        $lib = new \Tpwd\KeSearch\Lib\Pluginbase();
        $this->assertEquals('', $lib->renderOrdering());

        // Test with sortByVisitor = empty
        $this->conf = array(
            'showSortInFrontend' => true,
            'sortByVisitor' => '',
        );
        $this->numberOfResults = 35;
        $this->assertEquals('', $lib->renderOrdering());

        // Test with numberOfResults = 0
        $this->conf = array(
            'showSortInFrontend' => true,
            'sortByVisitor' => 'sortdate,title,tstamp',
        );
        $this->numberOfResults = 0;
        $this->assertEquals('', $lib->renderOrdering());
    }
}