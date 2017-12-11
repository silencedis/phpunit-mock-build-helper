<?php

namespace SilenceDis\PHPUnitMockHelper\Test;

use PHPUnit\Framework\TestCase;
use SilenceDis\PHPUnitMockHelper\Exception\InvalidMockTypeException;
use SilenceDis\PHPUnitMockHelper\MockHelper;

/**
 * Class MockHelperTest
 *
 * @author Yurii Slobodeniuk <silencedis@gmail.com>
 */
class MockHelperTest extends TestCase
{
    /**
     * @var MockHelper
     */
    private $mockHelper;

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        $this->mockHelper = new MockHelper(new Fixture\TestCase());

        parent::setUp();
    }

    # region getMockMethod

    /**
     * @dataProvider dataGetMockMethod
     * @param string $testValue A test value of mock type
     * @param string $expectedResult An expected result of method executing
     */
    public function testGetMockMethod($testValue, $expectedResult)
    {
        $mockHelper = $this->mockHelper;
        $closure = $this->_getMockMethodClosure($mockHelper);
        $actualResult = $closure($testValue);
        $this->assertEquals($expectedResult, $actualResult);
    }

    public function dataGetMockMethod()
    {
        return [
            [MockHelper::MOCK_TYPE_DEFAULT, 'getMock'],
            [MockHelper::MOCK_TYPE_ABSTRACT, 'getMockForAbstractClass'],
            [MockHelper::MOCK_TYPE_TRAIT, 'getMockForTrait'],
        ];
    }

    public function testGetMockMethodThrowsException()
    {
        $mockHelper = $this->mockHelper;
        $closure = $this->_getMockMethodClosure($mockHelper);
        $this->expectException(InvalidMockTypeException::class);
        $testInvalidParameter = '0123456789';
        $closure($testInvalidParameter);
    }

    private function _getMockMethodClosure(MockHelper $mockHelper): callable
    {
        $methodReflection = new \ReflectionMethod($mockHelper, 'getMockMethod');

        return $methodReflection->getClosure($mockHelper);
    }

    # end region
}
