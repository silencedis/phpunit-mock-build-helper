<?php

namespace SilenceDis\PHPUnitMockHelper;

use PHPUnit\Framework\TestCase;
use SilenceDis\PHPUnitMockHelper\Exception\InvalidMockTypeException;

/**
 * Class MockHelper
 *
 * @author Yurii Slobodeniuk <silencedis@gmail.com>
 */
class MockHelper
{
    const MOCK_TYPE_DEFAULT = 'default';
    const MOCK_TYPE_ABSTRACT = 'abstract';
    const MOCK_TYPE_TRAIT = 'trait';

    /**
     * An instance of TestCase whick is used to create mock builder through it.
     * @var \PHPUnit\Framework\TestCase
     */
    private $testCase;

    /**
     * A map of mock types to mock method names.
     * @var array
     */
    private $mockTypesToMethodsMap = [
        self::MOCK_TYPE_DEFAULT => 'getMock',
        self::MOCK_TYPE_ABSTRACT => 'getMockForAbstractClass',
        self::MOCK_TYPE_TRAIT => 'getMockForTrait',
    ];

    /**
     * MockHelper constructor.
     *
     * @param \PHPUnit\Framework\TestCase $testCase
     */
    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;
    }

    /**
     * General method for mocking objects
     *
     * @param string $objectClassName
     * @param array[] $configurations A variable number of configurations for creating of mock.
     * You can use this feature, for example, to gather a several configurations from different sources
     * and then just pass them to the `mockObject` method instead of merging them.
     * It will merge them itself.
     *
     * Each configuration must be an array. Otherwise you will get an error.
     * As default each item of array is considered as a name of property for which a getter may exist in the original mock builder.
     * For example, the parameter `constructorArgs` actually will be used to make the method name `setConstructorArgs`.
     * The values of these parameters are used as values which must be passed to the matched methods.
     *
     * But there are also special parameters that are handled in their own way.
     * They are:
     * - `constructor` - It's a shortcut of the `disableOriginalConstructor` parameter of the original mock builder.
     *   The value of parameter constructor is used to set the parameter `disableOriginalConstructor`.
     *   The value of the `constructor` must be a boolean value. The inverted boolean value will be set
     *   to the parameter `disableOriginalConstructor`.
     *   For example, if you'll set `constructor => false`, the parameter `disableOriginalConstructor` will get
     *   the value `false`.
     *   Note that the original parameter `disableOriginalConstructor` has a higher priority than parameter `constructor`.
     *   If the parameter `disableOriginalConstructor` is set, the parameter `constructor` will be ignored.
     * - `willReturn` - It's a shortcut for the separated setting of returned values for methods.
     *   It must be an array.
     *   The keys of the array are considered as method names.
     *   The values of the array are considered as values, that must be returned by the appropriate methods.
     *   ```php
     *   // This part of configuration ...
     *   [
     *       // ...
     *       'willReturn' => [
     *           'method1' => 'value1',
     *           'method2' => 'value2',
     *       ]
     *       // ...
     *   ]
     *
     *   // ... equals to
     *   $mock->method('method1')->willReturn('value1');
     *   $mock->method('method2')->willReturn('value2');
     *   ```
     * - `will` - It's similar to `willReturn` but the method `will()` will used instead of `willReturn()`.
     * - `mockType` - It's a type of mock.
     *   Allowed values are:
     *   - `default` (MockHelper::MOCK_TYPE_DEFAULT)
     *   - `abstract` (MockHelper::MOCK_TYPE_ABSTRACT)
     *   - `trait` (MockHelper::MOCK_TYPE_TRAIT)
     *   The value of this parameter is used to select a method of the original mock builder that must be used to create a mock.
     *
     * @return \PHPUnit_Framework_MockObject_MockObject
     * @throws InvalidMockTypeException
     */
    public function mockObject($objectClassName, ...$configurations)
    {
        foreach ($configurations as &$configuration) {
            $this->prepareMockConfig($configuration);
        }

        if (empty($configurations)) {
            $config = [];
        } elseif (count($configurations) == 1) {
            $config = reset($configurations);
        } else {
            $config = call_user_func_array('array_replace_recursive', $configurations);
        }

        $willReturn = $this->pullOutArrayValue($config, 'willReturn', []);
        $will = $this->pullOutArrayValue($config, 'will');
        $mockType = $this->pullOutArrayValue($config, 'mockType', self::MOCK_TYPE_DEFAULT);

        // 'methods' must be null for 'getMock' to not replace any element
        // (unlike 'getMockForAbstractClass')
        if (empty($config['methods'])) {
            if ($mockType == self::MOCK_TYPE_DEFAULT) {
                $config['methods'] = null;
            }
        }

        $mockBuilder = $this->testCase->getMockBuilder($objectClassName);

        foreach ($config as $property => $value) {
            $method = "set$property";
            if (method_exists($mockBuilder, $method)) {
                $mockBuilder->$method($value);
            }
        }

        if ($this->getArrayValue($config, 'disableOriginalConstructor', true)) {
            $mockBuilder->disableOriginalConstructor();
        }

        $mockMethod = $this->getMockMethod($mockType);
        /** @var \PHPUnit_Framework_MockObject_MockObject $mock */
        $mock = $mockBuilder->$mockMethod();

        foreach ($willReturn as $method => $value) {
            $mock->method($method)->willReturn($value);
        }

        foreach ($will as $method => $value) {
            $mock->method($method)->will($value);
        }

        return $mock;
    }

    /**
     * Index keys of the item "setMethods" using its values
     *
     * @param array $config
     */
    protected function prepareMockConfig(array &$config = [])
    {
        if (!isset($config['willReturn'])) {
            $config['willReturn'] = [];
        }

        if (!empty($config['methods']) && is_array($config['methods'])) {
            $methodNames = [];
            foreach ($config['methods'] as $key => $value) {
                $methodName = is_numeric($key) ? $value : $key;
                $methodNames[$methodName] = $methodName;
                if (!is_numeric($key)) {
                    $config['willReturn'][$key] = $value;
                }
            }
            $config['methods'] = $methodNames;
        }

        if (!isset($config['will'])) {
            $config['will'] = [];
        }

        if (isset($config['constructor'], $config['disableOriginalConstructor'])) {
            unset($config['constructor']);
        }

        if (isset($config['constructor'])) {
            $config['disableOriginalConstructor'] = !$config['constructor'];
            unset($config['constructor']);
        }
    }

    /**
     * Returns a name of method for getting an instance of mock.
     *
     * @param string $mockType A mock type.
     *
     * @return string
     * @throws InvalidMockTypeException
     */
    protected function getMockMethod($mockType)
    {
        if (!isset($this->mockTypesToMethodsMap[$mockType])) {
            throw new InvalidMockTypeException();
        }

        return $this->mockTypesToMethodsMap[$mockType];
    }

    # region helper methods

    /**
     * Returns an array item value.
     * If the specified array key doesn't exist, the specified default value will be returned.
     *
     * @param array $array An array in which the value is looked for.
     * @param string $key A key of the array by which the value is looked for.
     * @param null $defaultValue A default value which will be returned if the specified key doesn't exist in the array.
     *
     * @return mixed|null
     */
    private function getArrayValue(array &$array, string $key, $defaultValue = null)
    {
        if (!array_key_exists($key, $array)) {
            return $defaultValue;
        }

        return $array[$key];
    }

    /**
     * Returns an array item value and removes it from the array.
     *
     * @param array $array An array from which the value is pulled out.
     * @param string $key A key of the array.
     * @param mixed $defaultValue A default value which will be returned if the specified key doesn't exist in the array.
     *
     * @return mixed
     */
    private function pullOutArrayValue(array &$array, string $key, $defaultValue = null)
    {
        if (!array_key_exists($key, $array)) {
            return $defaultValue;
        }

        $value = $array[$key];
        unset($array[$key]);

        return $value;
    }

    # endregion
}
