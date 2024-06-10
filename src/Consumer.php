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

namespace bang\queue;

/**
 * Interface Consumer
 */
interface Consumer
{
    public function consume($data);
}