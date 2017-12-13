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
     * @group getMockMethod
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

    # region prepareMockConfig

    /**
     * @group prepareMockConfig
     * @dataProvider dataPrepareMockConfig
     * @param array $testConfig
     * @param array $expectedResult
     */
    public function testPrepareMockConfig(array $testConfig, array $expectedResult)
    {
        $mockHelper = $this->mockHelper;
        $closure = $this->_getPrepareMockConfigClosure($mockHelper);
        $closure($testConfig);
        $actualResult = $testConfig; // The tested method takes a parameter by reference and changes it
        $this->assertEquals(
            json_encode($expectedResult, JSON_PRETTY_PRINT),
            json_encode($actualResult, JSON_PRETTY_PRINT)
        );
    }

    public function dataPrepareMockConfig()
    {
        return [
            /*
             * If the configuration is empty, some default parameters will be initialized
             */
            [
                'testConfig' => [
                ],
                'expectedResult' => [
                    'willReturn' => [],
                    'will' => [],
                ],
            ],
            /*
             * Items of the "methods" array that have a non-numeric key will be handled.
             * Such keys will be considered as method names that must be mocked.
             * The matched against values will be considered as values that must be returned by the mocked method.
             * This data will be used to add a new configuration part to the "willReturn" parameter.
             * The key of the handled item of "methods" will be replaced by a numeric value.
             */
            [
                'testConfig' => [
                    'methods' => [
                        'methodName' => 'value',
                    ],
                ],
                'expectedResult' => [
                    'methods' => [
                        // The method names are used as keys of array
                        // to prevent repeating of method names when merging configurations in the "mockObject" method
                        'methodName' => 'methodName',
                    ],
                    'willReturn' => [
                        'methodName' => 'value',
                    ],
                    'will' => [],
                ],
            ],
            [
                'testConfig' => [
                    'methods' => [
                        'methodName' => 'value',
                    ],
                ],
                'expectedResult' => [
                    'methods' => [
                        'methodName' => 'methodName',
                    ],
                    'willReturn' => [
                        'methodName' => 'value',
                    ],
                    'will' => [],
                ],
            ],
            /*
             * Test of handling of mixed "methods" data.
             * The configuration of "willReturn" was also added to the test configuration here
             * to see how the handled items of "methods" are added to the "willReturn".
             */
            [
                'testConfig' => [
                    'methods' => [
                        // The value that must be returned by this method may be specified in the "willRetrn" parameter
                        'methodName',
                        'anotherMethodName' => 'foo',
                        'oneMoreMethodName',
                    ],
                    'willReturn' => [
                        // The value "aaa" must be returned by the mocked method "methodName"
                        'methodName' => 'aaa',
                    ],
                ],
                'expectedResult' => [
                    'methods' => [
                        'methodName' => 'methodName',
                        'anotherMethodName' => 'anotherMethodName',
                        'oneMoreMethodName' => 'oneMoreMethodName',
                    ],
                    'willReturn' => [
                        'methodName' => 'aaa',
                        'anotherMethodName' => 'foo', // This parameter has been pushed to the end of array.
                    ],
                    'will' => [],
                ],
            ],
            /*
             * Disable the constructor using the "constructor" parameter.
             * The value of "constructor" parameter is considered as a desired value of constructor activity.
             * It's used to initialize the value of "disableOriginalConstructorParameter".
             * If `constructor === false`, the `disableOriginalConstructor` value will be set to `true`
             * and vise versa.
             */
            [
                'testConfig' => [
                    'constructor' => false,
                ],
                'expectedResult' => [
                    'willReturn' => [],
                    'will' => [],
                    'disableOriginalConstructor' => true,
                ],
            ],
            [
                'testConfig' => [
                    'constructor' => true,
                ],
                'expectedResult' => [
                    'willReturn' => [],
                    'will' => [],
                    'disableOriginalConstructor' => false,
                ],
            ],
            /*
             * If both `disableOriginalConstructor` and `constructor` parameters has been set,
             * only the value of `disableOriginalConstructor` will be considered.
             * The `constructor` parameter will be removed.
             */
            [
                'testConfig' => [
                    'disableOriginalConstructor' => true,
                    'constructor' => true,
                ],
                'expectedResult' => [
                    'disableOriginalConstructor' => true,
                    // * Unlike the previous test set
                    // the following parameters are specified after the `disableOriginalConstructor` parameter.
                    // This is because they are initialized automatically,
                    // and the `disableOriginalConstructor` was specified in the original configuration
                    // and wasn't handled at all.
                    'willReturn' => [],
                    'will' => [],
                ],
            ],
        ];
    }

    private function _getPrepareMockConfigClosure(MockHelper $mockHelper): callable
    {
        $methodReflection = new \ReflectionMethod($mockHelper, 'prepareMockConfig');

        return $methodReflection->getClosure($mockHelper);
    }

    # end region
}
