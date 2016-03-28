<?php
/**
 * Copyright 2010-2016 Pavel Terentyev <pavel.terentyev@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */

/**
 * Class SimpleConsole_Autoload
 */
class SimpleConsole_Autoload
{
    public static function register()
    {
        spl_autoload_register(['SimpleConsole_Autoload', 'autoload']);

    }

    /**
     * @param $classname
     * @return bool
     */
    public static function autoload($classname)
    {
        $classpath = realpath(__DIR__ . DIRECTORY_SEPARATOR.'..' . DIRECTORY_SEPARATOR . str_replace('_', DIRECTORY_SEPARATOR,
                $classname) . '.php');
        if (!file_exists($classpath)) {
            return false;
        }
        /** @noinspection PhpIncludeInspection */
        require $classpath;
        return true;
    }
}
