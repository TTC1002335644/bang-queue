<?php
/**
 * This file is part of thinkphp.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    Bang<1002335644@qq.com>
 */
declare (strict_types=1);

namespace bang\queue\tool;

class Reflection
{
    /**
     * 获取某个Class的静态属性值
     * @param string $class
     * @param string|null $propertyName
     * @return mixed
     * @throws \ReflectionException
     */
    public static function getStaticProperties(string $class, ?string $propertyName)
    {
        $reflectionClass = new \ReflectionClass($class);
        try {
            $propertyValue = $reflectionClass->getProperty($propertyName)->getValue();
            return $propertyValue;
        } catch (\ReflectionException | \Exception $e) {
            return null;
        } catch (\Throwable $throwable){
            return null;
        }
    }


}